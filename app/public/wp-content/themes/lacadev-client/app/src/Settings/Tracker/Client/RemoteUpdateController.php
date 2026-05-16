<?php

namespace App\Settings\Tracker\Client;

use App\Settings\MaintenanceModeManager;
use App\Settings\Tracker\MaintenanceSnapshot;
use App\Settings\Tracker\RemoteUpdateExecutor;
use App\Settings\Tracker\RemoteUpdateHistory;
use App\Settings\Tracker\RemoteUpdateHistoryStore;
use App\Settings\Tracker\RemoteUpdateMeta;
use App\Settings\Tracker\RemoteUpdatePolicy;
use App\Settings\Tracker\RemoteUpdatePreflight;
use App\Settings\Tracker\RemoteUpdateRequestValidator;
use App\Settings\Tracker\TrackerClientRequestHandler;

class RemoteUpdateController
{
    /**
     * @var callable
     */
    private $sendLogs;

    /**
     * @var callable
     */
    private $hasTrackerEventTable;

    public function __construct(callable $sendLogs, callable $hasTrackerEventTable)
    {
        $this->sendLogs = $sendLogs;
        $this->hasTrackerEventTable = $hasTrackerEventTable;
    }

    public function registerClientRequestEndpoint(): void
    {
        register_rest_route('laca/v1', '/client/request', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleClientRequest'],
            'permission_callback' => '__return_true',
            'args'                => [
                'request_type' => ['required' => false, 'sanitize_callback' => 'sanitize_key'],
                'message' => ['required' => true, 'sanitize_callback' => 'sanitize_textarea_field'],
                'contact_name' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
                'contact_email' => ['required' => false, 'sanitize_callback' => 'sanitize_email'],
                'page_url' => ['required' => false, 'sanitize_callback' => 'esc_url_raw'],
            ],
        ]);
    }

    public function handleClientRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        return TrackerClientRequestHandler::handle(
            $request,
            [\App\Settings\LacaDevTrackerClient::class, 'isConfigured'],
            $this->hasTrackerEventTable,
            fn(array $logs, bool $blocking, string $channel, array $context): bool => ($this->sendLogs)($logs, $blocking, $channel, $context)
        );
    }

    public function registerRemoteUpdateEndpoint(): void
    {
        register_rest_route('laca/v1', '/remote-update', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleRemoteUpdate'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handleRemoteUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $validated = RemoteUpdateRequestValidator::validate(
            $request->get_json_params() ?: [],
            \App\Settings\LacaDevTrackerClient::getSecretKey()
        );

        if (empty($validated['ok'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => (string) ($validated['message'] ?? 'Yêu cầu không hợp lệ.'),
            ], (int) ($validated['status'] ?? 400));
        }

        $action = (string) $validated['action'];
        $slug = (string) $validated['slug'];
        $params = (array) ($validated['params'] ?? []);

        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $preflight = RemoteUpdatePreflight::check($action, $slug);
        if (!empty($validated['dry_run'])) {
            return new \WP_REST_Response([
                'success' => !empty($preflight['ok']),
                'message' => !empty($preflight['ok']) ? 'Preflight hoàn tất.' : 'Preflight không đạt.',
                'preflight' => $preflight,
                'snapshot' => MaintenanceSnapshot::capture($action, $slug),
            ], !empty($preflight['ok']) ? 200 : 400);
        }

        if (!empty($preflight['skip'])) {
            $msg = (string) ($preflight['message'] ?? 'Không có cập nhật cần chạy.');
            $this->recordMaintenanceEvent($action, $slug, 'skipped', $msg, [
                'preflight' => $preflight,
                'snapshot_before' => MaintenanceSnapshot::capture($action, $slug),
            ]);

            return new \WP_REST_Response([
                'success' => true,
                'message' => $msg,
                'preflight' => $preflight,
            ]);
        }

        if (empty($preflight['ok'])) {
            $msg = 'Preflight không đạt: ' . implode(' ', (array) ($preflight['errors'] ?? []));
            $this->recordMaintenanceEvent($action, $slug, 'failed', $msg, [
                'preflight' => $preflight,
                'snapshot_before' => MaintenanceSnapshot::capture($action, $slug),
            ]);

            return new \WP_REST_Response([
                'success' => false,
                'message' => $msg,
                'preflight' => $preflight,
            ], 400);
        }

        $skin = new \Automatic_Upgrader_Skin();
        $snapshotBefore = MaintenanceSnapshot::capture($action, $slug);
        $useMaintenance = RemoteUpdatePolicy::shouldUseTemporaryMaintenance($action, $params);
        $maintenanceOwner = 'remote_update_' . md5($action . '|' . $slug . '|' . microtime(true));
        $temporaryMaintenanceEnabled = $useMaintenance
            ? MaintenanceModeManager::activateTemporary($maintenanceOwner, 30 * MINUTE_IN_SECONDS)
            : false;

        set_transient('_laca_remote_update_in_progress', [
            'action' => $action,
            'slug' => $slug,
            'started_at' => current_time('mysql'),
            'preflight' => $preflight,
            'snapshot_before' => $snapshotBefore,
            'temporary_maintenance' => $temporaryMaintenanceEnabled,
        ], HOUR_IN_SECONDS);

        $execution = RemoteUpdateExecutor::execute($action, $slug, $skin);
        if (!empty($execution['invalid'])) {
            MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
            delete_transient('_laca_remote_update_in_progress');

            return new \WP_REST_Response([
                'success' => false,
                'message' => (string) ($execution['message'] ?? 'Action không hợp lệ.'),
            ], 400);
        }

        if (!empty($execution['skip'])) {
            $msg = (string) ($execution['message'] ?? 'Không có cập nhật cần chạy.');
            $meta = RemoteUpdateMeta::build(
                $preflight,
                $snapshotBefore,
                MaintenanceSnapshot::capture($action, $slug),
                $temporaryMaintenanceEnabled
            );
            $this->recordMaintenanceEvent($action, $slug, 'skipped', $msg, $meta);
            MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
            delete_transient('_laca_remote_update_in_progress');

            return new \WP_REST_Response(['success' => true, 'message' => $msg]);
        }

        $result = $execution['result'] ?? null;
        $label = (string) ($execution['label'] ?? $action);

        if (is_wp_error($result)) {
            $msg = "Cập nhật {$label} thất bại: " . $result->get_error_message();
            $meta = RemoteUpdateMeta::build(
                $preflight,
                $snapshotBefore,
                MaintenanceSnapshot::capture($action, $slug),
                $temporaryMaintenanceEnabled,
                RemoteUpdatePolicy::rollbackNote($action, $slug)
            );
            $this->recordMaintenanceEvent($action, $slug, 'failed', $msg, $meta);
            ($this->sendLogs)([['type' => 'other', 'content' => $msg, 'level' => 'critical', 'meta' => $meta]]);
            MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
            delete_transient('_laca_remote_update_in_progress');

            return new \WP_REST_Response(['success' => false, 'message' => $msg], 500);
        }

        if ($result === false || $result === null) {
            $msg = "Cập nhật {$label} không thành công (có thể đã ở phiên bản mới nhất).";
            $meta = RemoteUpdateMeta::build(
                $preflight,
                $snapshotBefore,
                MaintenanceSnapshot::capture($action, $slug),
                $temporaryMaintenanceEnabled,
                RemoteUpdatePolicy::rollbackNote($action, $slug)
            );
            $this->recordMaintenanceEvent($action, $slug, 'skipped', $msg, $meta);
            ($this->sendLogs)([['type' => 'other', 'content' => $msg, 'level' => 'warning', 'meta' => $meta]]);
            MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
            delete_transient('_laca_remote_update_in_progress');

            return new \WP_REST_Response(['success' => false, 'message' => $msg]);
        }

        $successMsg = "✅ Đã cập nhật {$label} thành công từ lệnh remote.";
        $meta = RemoteUpdateMeta::build(
            $preflight,
            $snapshotBefore,
            MaintenanceSnapshot::capture($action, $slug),
            $temporaryMaintenanceEnabled
        );
        $this->recordMaintenanceEvent($action, $slug, 'success', $successMsg, $meta);
        ($this->sendLogs)([['type' => 'deployment', 'content' => $successMsg, 'level' => 'info', 'meta' => $meta]]);
        MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
        delete_transient('_laca_remote_update_in_progress');

        return new \WP_REST_Response(['success' => true, 'message' => $successMsg]);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function recordMaintenanceEvent(string $action, string $slug, string $status, string $message, array $meta = []): void
    {
        update_option(
            \App\Settings\LacaDevTrackerClient::OPT_REMOTE_HISTORY,
            RemoteUpdateHistoryStore::append(
                get_option(\App\Settings\LacaDevTrackerClient::OPT_REMOTE_HISTORY, []),
                $action,
                $slug,
                $status,
                $message,
                $meta,
                current_time('mysql')
            ),
            false
        );
    }
}
