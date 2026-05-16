<?php

namespace App\Settings\Tracker\Client;

use App\Databases\TrackerEventTable;
use App\Settings\BlockSyncReceiver;
use App\Settings\Tracker\TrackerClientConfig;
use App\Settings\Tracker\TrackerHealthSummary;
use App\Settings\Tracker\TrackerHttpTransport;
use App\Settings\Tracker\TrackerMaintenanceTimelineBuilder;
use App\Settings\Tracker\TrackerQueuePolicy;
use App\Settings\Tracker\TrackerShortcodeRenderer;

class DeliveryManager
{
    /**
     * @var callable
     */
    private $hasTrackerEventTable;

    /**
     * @var callable
     */
    private $remoteHistory;

    /**
     * @var callable
     */
    private $captureMaintenanceSnapshot;

    /**
     * @param callable():bool $hasTrackerEventTable
     * @param callable():array $remoteHistory
     * @param callable(string, string):array $captureMaintenanceSnapshot
     */
    public function __construct(callable $hasTrackerEventTable, callable $remoteHistory, callable $captureMaintenanceSnapshot)
    {
        $this->hasTrackerEventTable = $hasTrackerEventTable;
        $this->remoteHistory = $remoteHistory;
        $this->captureMaintenanceSnapshot = $captureMaintenanceSnapshot;
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @param array<string, mixed> $context
     */
    public function sendLogs(array $logs, bool $blocking = false, string $channel = 'tracker', array $context = []): bool
    {
        $endpoint  = TrackerClientConfig::endpoint();
        $secretKey = TrackerClientConfig::secretKey();

        if ($endpoint === '' || $secretKey === '' || $logs === []) {
            $this->recordHealth(false, 'Tracker chưa được cấu hình.');
            return false;
        }

        $payload = apply_filters(\App\Contracts\HookNames::TRACKER_PAYLOAD, [
            'secret_key' => $secretKey,
            'site_url' => get_bloginfo('url'),
            'logs' => $logs,
        ], $logs);

        $eventType = sanitize_key((string) ($logs[0]['type'] ?? 'other'));

        if (($this->hasTrackerEventTable)()) {
            $eventId = TrackerEventTable::create($channel, $eventType, $payload, $context);
            $event = TrackerEventTable::find($eventId);

            if (!$event) {
                $this->recordHealth(false, 'Không tạo được tracker event cục bộ.');
                return false;
            }

            if (!$blocking) {
                $this->scheduleQueueSoon();
                return true;
            }

            return $this->deliverStoredEvent($event, $blocking);
        }

        $result = TrackerHttpTransport::post(TrackerClientConfig::endpoint(), $payload, $blocking);
        $this->recordHealth($result['success'], $result['error'] ?? '', $result['code'] ?? null);

        return $result['success'];
    }

    /**
     * @param array<string, mixed> $event
     */
    private function deliverStoredEvent(array $event, bool $blocking = true): bool
    {
        $payload = TrackerEventTable::decodeJsonColumn($event['payload'] ?? '');
        if (empty($payload)) {
            TrackerEventTable::markFailed((int) $event['id'], (int) ($event['attempts'] ?? 0), 'Payload cục bộ không hợp lệ.');
            $this->recordHealth(false, 'Payload cục bộ không hợp lệ.');
            return false;
        }

        $attempts = (int) ($event['attempts'] ?? 0) + 1;
        $result = TrackerHttpTransport::post(TrackerClientConfig::endpoint(), $payload, $blocking);

        if ($result['success']) {
            TrackerEventTable::markDelivered((int) $event['id'], $attempts);
            $this->recordHealth(true, '', $result['code'] ?? null);
            return true;
        }

        $error = $result['error'] ?: 'Không gửi được tracker event.';
        if (TrackerQueuePolicy::shouldFailPermanently($attempts, \App\Settings\LacaDevTrackerClient::MAX_ATTEMPTS)) {
            TrackerEventTable::markFailed((int) $event['id'], $attempts, $error);
        } else {
            TrackerEventTable::markRetry((int) $event['id'], $attempts, $error, $this->nextAttemptAt($attempts));
        }

        $this->recordHealth(false, $error, $result['code'] ?? null);

        return false;
    }

    public function processQueue(): void
    {
        if (!TrackerClientConfig::isConfigured() || !($this->hasTrackerEventTable)()) {
            return;
        }

        foreach (TrackerEventTable::findPending(10) as $event) {
            $this->deliverStoredEvent($event, true);
        }

        TrackerEventTable::purgeOld(90);
    }

    private function scheduleQueueSoon(): void
    {
        if (get_transient('_laca_tracker_retry_soon_scheduled')) {
            return;
        }

        wp_schedule_single_event(time() + 60, \App\Settings\LacaDevTrackerClient::CRON_RETRY);
        set_transient('_laca_tracker_retry_soon_scheduled', 1, 90);
    }

    /**
     * @param array<string, int> $updateCounts
     */
    public function sendHeartbeat(array $updateCounts): void
    {
        $this->sendLogs([[
            'type'    => 'heartbeat',
            'content' => 'Client heartbeat: ' . home_url('/'),
            'level'   => 'info',
            'meta'    => [
                'site_url' => home_url('/'),
                'site_name' => get_bloginfo('name'),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'theme' => get_stylesheet(),
                'parent_theme' => get_template(),
                'is_ssl' => is_ssl(),
                'tracker_health' => $this->getHealthSummary(),
                'updates_pending' => $updateCounts,
            ],
        ]], false, 'heartbeat');
    }

    /**
     * @param array<string, int> $updateCounts
     */
    public function sendMaintenanceSummary(array $updateCounts): void
    {
        $health = $this->getHealthSummary();
        $remoteHistory = array_values(array_filter(
            ($this->remoteHistory)(),
            static fn($item): bool => is_array($item)
                && !empty($item['time'])
                && strtotime((string) $item['time']) >= current_time('timestamp') - WEEK_IN_SECONDS
        ));
        $remoteStatusCounts = TrackerHealthSummary::countRemoteHistoryStatuses($remoteHistory);
        $blockDiagnostics = class_exists(BlockSyncReceiver::class) ? BlockSyncReceiver::getDiagnostics() : [];
        $blockDiagnosticCounts = TrackerHealthSummary::countBlockDiagnostics($blockDiagnostics);
        $eventSummary = ($this->hasTrackerEventTable)() ? TrackerEventTable::getSummarySince(7) : [];

        $lines = [
            'Báo cáo bảo trì 7 ngày: ' . home_url('/'),
            'Tracker queue: ' . (int) $health['queued'] . ' chờ, ' . (int) $health['retry'] . ' retry, ' . (int) $health['failed'] . ' lỗi.',
            'Remote maintenance: ' . count($remoteHistory) . ' thao tác, ' . (int) ($remoteStatusCounts['failed'] ?? 0) . ' lỗi.',
            'Pending updates: ' . (int) $updateCounts['plugins'] . ' plugin, ' . (int) $updateCounts['themes'] . ' theme, core ' . ((int) $updateCounts['core'] > 0 ? 'có bản mới' : 'ổn định') . '.',
            'Block diagnostics: ' . (int) $blockDiagnosticCounts['warnings'] . ' warnings, ' . (int) $blockDiagnosticCounts['errors'] . ' errors.',
        ];

        if (!empty($eventSummary['by_channel'])) {
            $channelParts = [];
            foreach ($eventSummary['by_channel'] as $channel => $count) {
                $channelParts[] = $channel . ': ' . $count;
            }
            $lines[] = 'Events: ' . implode(', ', $channelParts) . '.';
        }

        $this->sendLogs([[
            'type' => 'maintenance_summary',
            'content' => implode("\n", $lines),
            'level' => ((int) $health['failed'] > 0 || (int) $health['retry'] > 0) ? 'warning' : 'info',
            'meta' => [
                'period_days' => 7,
                'tracker_health' => $health,
                'updates_pending' => $updateCounts,
                'remote_updates' => array_slice($remoteHistory, 0, 10),
                'remote_status_counts' => $remoteStatusCounts,
                'block_diagnostics' => [
                    'counts' => $blockDiagnosticCounts,
                    'items' => array_slice($blockDiagnostics, 0, 10),
                ],
                'event_summary' => $eventSummary,
            ],
        ]], false, 'summary', ['period_days' => 7]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getHealthSummary(): array
    {
        $health = get_option(\App\Settings\LacaDevTrackerClient::OPT_HEALTH, []);
        if (!is_array($health)) {
            $health = [];
        }

        $queueCounts = [
            'queued' => 0,
            'retry' => 0,
            'failed' => 0,
            'delivered' => 0,
        ];

        if (($this->hasTrackerEventTable)()) {
            foreach (array_keys($queueCounts) as $status) {
                $queueCounts[$status] = TrackerEventTable::countByStatus($status);
            }
        }

        return TrackerHealthSummary::build($health, TrackerClientConfig::isConfigured(), $queueCounts);
    }

    public function renderSupportCenterShortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'title' => 'Gửi yêu cầu hỗ trợ',
            'class' => '',
        ], $atts, 'laca_support_center');

        $endpoint = rest_url('laca/v1/client/request');
        $formId = 'laca-support-' . wp_generate_uuid4();
        $extraClass = sanitize_html_class((string) $atts['class']);

        return TrackerShortcodeRenderer::supportCenter(
            (string) $atts['title'],
            $extraClass,
            $endpoint,
            $formId,
            get_permalink() ?: home_url('/')
        );
    }

    public function renderMaintenanceTimelineShortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'title' => 'Lịch sử chăm sóc website',
            'limit' => 20,
            'class' => '',
        ], $atts, 'laca_maintenance_timeline');

        $limit = max(1, min(50, (int) $atts['limit']));
        $items = TrackerMaintenanceTimelineBuilder::build(
            ($this->remoteHistory)(),
            (array) get_option('laca_block_activity_log', []),
            ($this->hasTrackerEventTable)() ? TrackerEventTable::getRecent(80) : [],
            $limit
        );
        $extraClass = sanitize_html_class((string) $atts['class']);

        return TrackerShortcodeRenderer::maintenanceTimeline((string) $atts['title'], $extraClass, $items);
    }

    private function nextAttemptAt(int $attempts): string
    {
        return TrackerQueuePolicy::nextAttemptAt(
            $attempts,
            current_time('timestamp'),
            static fn(int $timestamp): string => date_i18n('Y-m-d H:i:s', $timestamp)
        );
    }

    private function recordHealth(bool $success, string $error = '', ?int $code = null): void
    {
        $health = get_option(\App\Settings\LacaDevTrackerClient::OPT_HEALTH, []);
        if (!is_array($health)) {
            $health = [];
        }

        $health['configured'] = TrackerClientConfig::isConfigured();
        $health['last_attempt_at'] = current_time('mysql');
        $health['last_http_code'] = $code;

        if ($success) {
            $health['last_success_at'] = current_time('mysql');
            $health['last_error'] = '';
            $health['last_failure_at'] = $health['last_failure_at'] ?? '';
        } else {
            $health['last_failure_at'] = current_time('mysql');
            $health['last_error'] = $error;
        }

        update_option(\App\Settings\LacaDevTrackerClient::OPT_HEALTH, $health, false);
    }
}
