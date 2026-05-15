<?php

namespace App\Settings;

use App\Contracts\HookNames;
use App\Databases\TrackerEventTable;
use App\Settings\Tracker\ClientSupportRequestBuilder;
use App\Settings\Tracker\MaintenanceSnapshot;
use App\Settings\Tracker\RemoteUpdateExecutor;
use App\Settings\Tracker\RemoteUpdateHistoryStore;
use App\Settings\Tracker\RemoteUpdateMeta;
use App\Settings\Tracker\RemoteUpdatePolicy;
use App\Settings\Tracker\RemoteUpdateHistory;
use App\Settings\Tracker\RemoteUpdatePreflight;
use App\Settings\Tracker\RemoteUpdateRequestValidator;
use App\Settings\Tracker\SuspiciousFileScanner;
use App\Settings\Tracker\TrackerClientRequestHandler;
use App\Settings\Tracker\TrackerClientConfig;
use App\Settings\Tracker\TrackerFileIntegrity;
use App\Settings\Tracker\TrackerHealthSummary;
use App\Settings\Tracker\TrackerHttpTransport;
use App\Settings\Tracker\TrackerMaintenanceTimelineBuilder;
use App\Settings\Tracker\TrackerQueuePolicy;
use App\Settings\Tracker\TrackerShortcodeRenderer;
use App\Settings\Tracker\TrackerTimelinePresenter;
use App\Support\ClientIpResolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LacaDev Tracker Client
 *
 * Chạy ở web CLIENT để tự động gửi log & cảnh báo về hệ thống
 * quản lý dự án (lacadev.com). Gửi khi:
 *   - Plugin/Theme/Core được cập nhật hoặc cài mới
 *   - Plugin bị xóa hoặc kích hoạt
 *   - File lạ xuất hiện ở thư mục gốc, uploads, mu-plugins (cron hàng giờ)
 *   - Hàng ngày: digest danh sách plugin/theme đang chờ update
 *
 * Cấu hình qua Carbon Fields (Laca Admin → 📡 Tracker):
 *   laca_tracker_endpoint   — REST URL của lacadev CMS
 *   laca_tracker_secret_key — Secret key của project
 *
 * Public client request endpoint:
 *   POST /wp-json/laca/v1/client/request
 */
class LacaDevTrackerClient
{
    // Carbon Fields field names (dùng với carbon_get_theme_option)
    const CF_ENDPOINT = 'laca_tracker_endpoint';
    const CF_SECRET   = 'laca_tracker_secret_key';

    // WP Cron hook names
    const CRON_HOURLY  = 'laca_tracker_hourly_scan';
    const CRON_DAILY   = 'laca_tracker_daily_digest';
    const CRON_RETRY   = 'laca_tracker_retry_queue';
    const CRON_HEARTBEAT = 'laca_tracker_heartbeat';
    const CRON_WEEKLY_SUMMARY = 'laca_tracker_weekly_summary';

    const OPT_HEALTH = '_laca_tracker_health';
    const OPT_REMOTE_HISTORY = '_laca_remote_update_history';
    const OPT_TABLE_INSTALL_CHECK = '_laca_tracker_events_install_checked';
    const MAX_ATTEMPTS = 5;

    /**
     * Thư mục cần quét file lạ (relative to ABSPATH)
     * Chỉ chứa các thư mục nhỏ/nguy hiểm cần scan liên tục.
     * Theme/plugin active được xử lý riêng với baseline filemtime.
     */
    const SUSPICIOUS_DIRS = [
        '',                          // Root (wp-config.php, .htaccess, index.php)
        'wp-content/uploads',        // Nơi hacker hay nhét shell
        'wp-content/mu-plugins',     // MU-plugin chạy tự động không cần kích hoạt
    ];

    /**
     * Option key lưu baseline filemtime cho theme/plugin đang active
     */
    const OPT_BASELINE = '_laca_tracker_file_baseline';

    /**
     * Option key để track danh sách plugin update đã biết
     * → tránh gửi alert trùng lặp mỗi lần page load
     */
    const OPT_KNOWN_UPDATES = '_laca_tracker_known_plugin_updates';

    // =========================================================================
    // KHỞI TẠO
    // =========================================================================

    public function __construct()
    {
        add_filter('cron_schedules', [$this, 'addCronSchedules']);

        // --- Event hooks ---
        add_action('upgrader_process_complete', [$this, 'onUpgraderComplete'], 20, 2);
        add_action('delete_plugin',             [$this, 'onDeletePlugin']);
        add_action('deleted_plugin',            [$this, 'afterDeletePlugin'], 10, 2);
        add_action('activated_plugin',          [$this, 'onActivatePlugin']);
        add_action('deactivated_plugin',        [$this, 'onDeactivatePlugin']);
        add_action(HookNames::BLOCK_SYNC_RECEIVED, [$this, 'onBlockSyncReceived'], 10, 3);

        // --- Phát hiện plugin cần update NGAY KHI WP check (không đợi cron) ---
        // Filter set_site_transient_update_plugins chạy mỗi khi WP lưu kết quả
        // check update mới từ wordpress.org → so sánh với lần trước, gửi alert ngay.
        add_filter('set_site_transient_update_plugins', [$this, 'onUpdateTransientSet']);

        // --- REST endpoint: nhận lệnh cập nhật từ xa từ lacadev.com ---
        add_action('rest_api_init', [$this, 'registerRemoteUpdateEndpoint']);
        add_action('rest_api_init', [$this, 'registerClientRequestEndpoint']);

        // --- Cron hàng giờ: quét file lạ ở thư mục nhạy cảm ---
        add_action(self::CRON_HOURLY, [$this, 'runHourlyScan']);
        if (!wp_next_scheduled(self::CRON_HOURLY)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOURLY);
        }

        // --- Cron hàng ngày: digest update pending + scan baseline theme/plugin ---
        add_action(self::CRON_DAILY, [$this, 'runDailyDigest']);
        if (!wp_next_scheduled(self::CRON_DAILY)) {
            // Chạy lúc 8:00 sáng (UTC+7 = 1:00 UTC)
            $nextRun = strtotime('tomorrow 01:00:00 UTC');
            wp_schedule_event($nextRun, 'daily', self::CRON_DAILY);
        }

        add_action(self::CRON_RETRY, [$this, 'processQueue']);
        $this->ensureRecurringEvent(self::CRON_RETRY, 'laca_five_minutes', time() + 5 * MINUTE_IN_SECONDS);

        add_action(self::CRON_HEARTBEAT, [$this, 'sendHeartbeat']);
        $this->ensureRecurringEvent(self::CRON_HEARTBEAT, 'twicedaily', time() + 10 * MINUTE_IN_SECONDS);

        add_action(self::CRON_WEEKLY_SUMMARY, [$this, 'sendMaintenanceSummary']);
        $this->ensureRecurringEvent(self::CRON_WEEKLY_SUMMARY, 'laca_weekly', $this->nextWeeklySummaryRun());

        add_shortcode('laca_support_center', [$this, 'renderSupportCenterShortcode']);
        add_shortcode('laca_maintenance_timeline', [$this, 'renderMaintenanceTimelineShortcode']);
    }

    public function addCronSchedules(array $schedules): array
    {
        $schedules['laca_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every 5 minutes', 'laca'),
        ];

        $schedules['laca_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Weekly', 'laca'),
        ];

        return $schedules;
    }

    private function nextWeeklySummaryRun(): int
    {
        $nextRun = strtotime('next monday 01:30:00 UTC');

        return $nextRun ? (int) $nextRun : time() + WEEK_IN_SECONDS;
    }

    private function ensureRecurringEvent(string $hook, string $schedule, int $timestamp): void
    {
        if (function_exists('wp_get_scheduled_event')) {
            $event = wp_get_scheduled_event($hook);
            if ($event && ($event->schedule ?? '') === $schedule) {
                return;
            }

            if ($event) {
                wp_clear_scheduled_hook($hook);
            }
        } elseif (wp_next_scheduled($hook)) {
            return;
        }

        wp_schedule_event($timestamp, $schedule, $hook);
    }


    // =========================================================================
    // EVENT HOOKS — gửi log tức thì
    // =========================================================================

    /**
     * Chạy ngay khi WP lưu kết quả check update plugin mới từ wordpress.org
     *
     * Hook: set_site_transient_update_plugins (filter, không phải action)
     * Phải return $value để không phá vỡ transient.
     *
     * Logic: so sánh tập hợp plugin-file trong response với lần lưu trước.
     * Nếu có plugin MỚI xuất hiện trong danh sách cần update (chưa có lần trước)
     * → gửi alert ngay lập tức, không đợi cron 8h sáng.
     */
    public function onUpdateTransientSet(mixed $value): mixed
    {
        // Không có response = không có plugin cần update
        if (empty($value->response) || !is_array($value->response)) {
            // KHÔNG delete known list — giữ để tránh re-alert khi WP check lại
            return $value;
        }

        $currentKeys = array_keys($value->response); // vd: ['litespeed-cache/litespeed-cache.php',...]
        sort($currentKeys);

        $knownKeys = (array) get_option(self::OPT_KNOWN_UPDATES, []);
        sort($knownKeys);

        // Tìm plugin MỚI (chưa có trong lần check trước)
        $newlyFound = array_diff($currentKeys, $knownKeys);

        if (!empty($newlyFound)) {
            $logs = [];
            foreach ($newlyFound as $pluginFile) {
                $data       = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
                $name       = $data['Name']    ?? $pluginFile;
                $current    = $data['Version'] ?? '?';
                $newVersion = $value->response[$pluginFile]->new_version ?? '?';

                $logs[] = [
                    'type'    => 'update_pending',
                    'content' => "⚠️ Plugin cần update: {$name}\n  Phiên bản hiện tại: {$current} → Bản mới: {$newVersion}",
                    'level'   => 'warning',
                ];
            }

            if (!empty($logs)) {
                $this->sendLogs($logs);
            }
        }

        // Cập nhật danh sách đã biết (dù có mới hay không) để tránh re-alert
        update_option(self::OPT_KNOWN_UPDATES, $currentKeys, false);

        return $value;
    }

    /**
     * Plugin/Theme/Core vừa được update hoặc cài mới
     */
    public function onUpgraderComplete(mixed $upgrader, array $options): void

    {
        $action = $options['action'] ?? '';
        $type   = $options['type']   ?? '';

        if ($action !== 'update' && $action !== 'install') {
            return;
        }

        $logs = [];

        if ($type === 'plugin') {
            $plugins = (array) ($options['plugins'] ?? []);
            if ($action === 'install' && !empty($upgrader->new_plugin_data)) {
                $name    = $upgrader->new_plugin_data['Name']    ?? 'Không rõ';
                $version = $upgrader->new_plugin_data['Version'] ?? '';
                $logs[]  = [
                    'type'    => 'plugin_install',
                    'content' => "Cài mới plugin: {$name}" . ($version ? " v{$version}" : ''),
                    'level'   => 'info',
                ];
            } else {
                foreach ($plugins as $plugin) {
                    $data    = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
                    $name    = $data['Name']    ?? $plugin;
                    $version = $data['Version'] ?? '';
                    $logs[]  = [
                        'type'    => 'plugin_update',
                        'content' => "Cập nhật plugin: {$name}" . ($version ? " → v{$version}" : ''),
                        'level'   => 'info',
                    ];
                }
            }
        } elseif ($type === 'theme') {
            $themes = (array) ($options['themes'] ?? []);
            foreach ($themes as $theme) {
                $data    = wp_get_theme($theme);
                $name    = $data->get('Name')    ?: $theme;
                $version = $data->get('Version') ?: '';
                $logs[]  = [
                    'type'    => 'theme_update',
                    'content' => "Cập nhật theme: {$name}" . ($version ? " → v{$version}" : ''),
                    'level'   => 'info',
                ];
            }
        } elseif ($type === 'core') {
            $wpVersion = get_bloginfo('version');
            $logs[]    = [
                'type'    => 'core_update',
                'content' => "Cập nhật WordPress Core → v{$wpVersion}",
                'level'   => 'info',
            ];
        }

        // Reset baseline sau khi update để tránh false positive
        if (!empty($logs)) {
            delete_option(self::OPT_BASELINE);
            $this->sendLogs($logs);
        }
    }

    /**
     * Plugin sắp bị xóa — lưu tên trước khi mất
     */
    public function onDeletePlugin(string $pluginFile): void
    {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        set_transient('_laca_deleting_plugin', $data['Name'] ?? $pluginFile, 60);
    }

    public function afterDeletePlugin(string $pluginFile, bool $deleted): void
    {
        if (!$deleted) {
            return;
        }
        $name = get_transient('_laca_deleting_plugin') ?: $pluginFile;
        delete_transient('_laca_deleting_plugin');

        $this->sendLogs([[
            'type'    => 'plugin_delete',
            'content' => "⚠️ Đã xóa plugin: {$name}",
            'level'   => 'warning',
        ]]);
    }

    /**
     * Plugin vừa được kích hoạt
     */
    public function onActivatePlugin(string $pluginFile): void
    {
        $data    = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        $name    = $data['Name']    ?? $pluginFile;
        $version = $data['Version'] ?? '';

        $this->sendLogs([[
            'type'    => 'plugin_activate',
            'content' => "✅ Kích hoạt plugin: {$name}" . ($version ? " v{$version}" : ''),
            'level'   => 'info',
        ]]);
    }

    /**
     * Plugin bị tắt (deactivate)
     */
    public function onDeactivatePlugin(string $pluginFile): void
    {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        $name = $data['Name'] ?? $pluginFile;

        $this->sendLogs([[
            'type'    => 'plugin_deactivate',
            'content' => "🔴 Tắt plugin: {$name}",
            'level'   => 'warning',
        ]]);
    }

    public function onBlockSyncReceived(string $blockName, string $version, bool $isUpdate): void
    {
        $diagnostics = class_exists(BlockSyncReceiver::class)
            ? (BlockSyncReceiver::getDiagnostics()[$blockName] ?? [])
            : [];

        $this->sendLogs([[
            'type'    => 'block_sync',
            'content' => ($isUpdate ? 'Cập nhật block' : 'Sync block mới') . ": {$blockName}" . ($version !== '' ? " v{$version}" : ''),
            'level'   => 'info',
            'meta'    => [
                'block_name' => sanitize_key($blockName),
                'version' => sanitize_text_field($version),
                'is_update' => $isUpdate,
                'diagnostics' => $diagnostics,
            ],
        ]], false, 'tracker', [
            'source' => 'block_sync',
            'block_name' => sanitize_key($blockName),
            'diagnostics' => $diagnostics,
        ]);
    }

    // =========================================================================
    // CRON HOURLY — quét file lạ ở thư mục nhạy cảm
    // =========================================================================

    /**
     * Quét hàng giờ: tìm file PHP/shell trong thư mục root, uploads, mu-plugins
     */
    public function runHourlyScan(): void
    {
        $found = SuspiciousFileScanner::scan(ABSPATH, self::SUSPICIOUS_DIRS);

        if (!empty($found)) {
            $list = implode("\n", array_map(fn($f) => '  - ' . $f, $found));
            $this->sendLogs([[
                'type'    => 'file_suspicious',
                'content' => "⚠️ Phát hiện file đáng ngờ:\n{$list}",
                'level'   => 'critical',
            ]]);
        }
    }

    // =========================================================================
    // CRON DAILY — digest update pending + baseline check
    // =========================================================================

    /**
     * Chạy hàng ngày: gửi danh sách plugin/theme chờ update + kiểm tra file integrity
     */
    public function runDailyDigest(): void
    {
        $logs = [];

        // 1. Kiểm tra plugin chờ update — chỉ gửi plugin MỚI so với known list
        $pluginUpdates = $this->getPendingPluginUpdates();
        $knownKeys = (array) get_option(self::OPT_KNOWN_UPDATES, []);
        $newPlugins = array_filter($pluginUpdates, function ($p) use ($knownKeys) {
            return !in_array($p['slug'] ?? '', $knownKeys, true);
        });
        if (!empty($newPlugins)) {
            $list   = implode("\n", array_map(fn($p) => "  - {$p['name']}: {$p['current']} → {$p['new']}", $newPlugins));
            $logs[] = [
                'type'    => 'update_pending',
                'content' => "Plugin mới chờ update (" . count($newPlugins) . "):\n{$list}",
                'level'   => 'warning',
            ];
        }

        // 2. Kiểm tra theme chờ update
        $themeUpdates = $this->getPendingThemeUpdates();
        if (!empty($themeUpdates)) {
            $list   = implode("\n", array_map(fn($t) => "  - {$t['name']}: {$t['current']} → {$t['new']}", $themeUpdates));
            $logs[] = [
                'type'    => 'update_pending',
                'content' => "🎨 Có " . count($themeUpdates) . " theme chờ update:\n{$list}",
                'level'   => 'warning',
            ];
        }

        // 3. Kiểm tra WordPress Core có update không
        $coreUpdate = $this->getPendingCoreUpdate();
        if ($coreUpdate) {
            $logs[] = [
                'type'    => 'update_pending',
                'content' => "🔄 WordPress Core: {$coreUpdate['current']} → {$coreUpdate['new']} (có bản mới)",
                'level'   => 'warning',
            ];
        }

        // 4. File integrity: check theme đang active và plugins đang bật
        $modifiedFiles = $this->checkFileIntegrity();
        if (!empty($modifiedFiles)) {
            $list   = implode("\n", array_map(fn($f) => "  - {$f}", $modifiedFiles));
            $logs[] = [
                'type'    => 'file_changed',
                'content' => "📝 Phát hiện file theme/plugin bị thay đổi:\n{$list}",
                'level'   => 'critical',
            ];
        }

        if (!empty($logs)) {
            $this->sendLogs($logs);
        }
    }

    /**
     * Danh sách plugin có bản update mới (chưa update)
     */
    private function getPendingPluginUpdates(): array
    {
        // Buộc WordPress fetch thông tin update mới nhất
        wp_update_plugins();

        $updates = get_site_transient('update_plugins');
        if (empty($updates->response)) {
            return [];
        }

        $result = [];
        foreach ($updates->response as $pluginFile => $data) {
            $installed = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
            $result[]  = [
                'slug'    => $pluginFile,
                'name'    => $installed['Name'] ?? $pluginFile,
                'current' => $installed['Version'] ?? '?',
                'new'     => $data->new_version ?? '?',
            ];
        }
        return $result;
    }

    /**
     * Danh sách theme có bản update mới
     */
    private function getPendingThemeUpdates(): array
    {
        wp_update_themes();

        $updates = get_site_transient('update_themes');
        if (empty($updates->response)) {
            return [];
        }

        $result = [];
        foreach ($updates->response as $themeSlug => $data) {
            $theme    = wp_get_theme($themeSlug);
            $result[] = [
                'name'    => $theme->get('Name') ?: $themeSlug,
                'current' => $theme->get('Version') ?: '?',
                'new'     => $data['new_version'] ?? '?',
            ];
        }
        return $result;
    }

    /**
     * Kiểm tra WordPress Core có update không
     */
    private function getPendingCoreUpdate(): ?array
    {
        wp_version_check();

        $updates = get_site_transient('update_core');
        if (empty($updates->updates)) {
            return null;
        }

        foreach ($updates->updates as $update) {
            if (($update->response ?? '') === 'upgrade') {
                return [
                    'current' => get_bloginfo('version'),
                    'new'     => $update->version ?? '?',
                ];
            }
        }
        return null;
    }

    /**
     * Kiểm tra file integrity của theme đang active + plugins đang bật
     * Dùng baseline filemtime: lần đầu lưu baseline, lần sau so sánh.
     */
    private function checkFileIntegrity(): array
    {
        $baseline = get_option(self::OPT_BASELINE, []);
        $comparison = TrackerFileIntegrity::compare(
            is_array($baseline) ? $baseline : [],
            $this->getIntegrityWatchPaths(),
            'file_exists',
            'filemtime',
            static fn(int $mtime): string => date('d/m/Y H:i', $mtime)
        );

        // Lưu baseline mới
        update_option(self::OPT_BASELINE, $comparison['current'], false);

        return $comparison['changed'];
    }

    /**
     * Danh sách file cần theo dõi integrity (key=abs path, value=relative label)
     */
    private function getIntegrityWatchPaths(): array
    {
        $paths = [];

        // Theme đang active — theo dõi file PHP + JS + CSS cấp 1
        $activeTheme    = get_stylesheet_directory();
        $themeSlug      = get_stylesheet();
        $themeFiles     = array_merge(
            glob($activeTheme . '/*.php')  ?: [],
            glob($activeTheme . '/*.js')   ?: [],
            glob($activeTheme . '/*.css')  ?: [],
            glob($activeTheme . '/functions.php') ?: []
        );
        foreach (array_unique($themeFiles) as $f) {
            $label = "themes/{$themeSlug}/" . basename($f);
            $paths[$f] = $label;
        }

        // functions.php trong thư mục con (child theme nếu có)
        $parentTheme = get_template_directory();
        if ($parentTheme !== $activeTheme) {
            $parentFunctions = $parentTheme . '/functions.php';
            if (file_exists($parentFunctions)) {
                $parentSlug          = get_template();
                $paths[$parentFunctions] = "themes/{$parentSlug}/functions.php";
            }
        }

        // Plugins đang kích hoạt — chỉ file chính (.php cùng tên thư mục)
        $activePlugins = (array) get_option('active_plugins', []);
        foreach ($activePlugins as $pluginRel) {
            $absPlugin = WP_PLUGIN_DIR . '/' . $pluginRel;
            if (file_exists($absPlugin)) {
                $paths[$absPlugin] = 'plugins/' . $pluginRel;
            }
        }

        return $paths;
    }

    // =========================================================================
    // INTERNAL — HTTP sender
    // =========================================================================

    public static function hasTrackerEventTable(): bool
    {
        return self::ensureTrackerEventTable();
    }

    private static function ensureTrackerEventTable(): bool
    {
        if (!class_exists(TrackerEventTable::class)) {
            return false;
        }

        if (TrackerEventTable::exists()) {
            return true;
        }

        $schemaVersion = defined('LACADEV_CLIENT_SCHEMA_VERSION') ? LACADEV_CLIENT_SCHEMA_VERSION : '1.0.0';
        $lastCheck = (string) get_option(self::OPT_TABLE_INSTALL_CHECK, '');
        $isCron = function_exists('wp_doing_cron') ? wp_doing_cron() : (defined('DOING_CRON') && DOING_CRON);
        if ($lastCheck !== $schemaVersion || is_admin() || $isCron) {
            TrackerEventTable::install();
            update_option(self::OPT_TABLE_INSTALL_CHECK, $schemaVersion, false);
        }

        return TrackerEventTable::exists();
    }

    /**
     * Gửi mảng logs về REST API của lacadev CMS.
     *
     * @param array<array{type: string, content: string, level?: string}> $logs
     */
    private function sendLogs(array $logs, bool $blocking = false, string $channel = 'tracker', array $context = []): bool
    {
        $endpoint  = self::getEndpoint();
        $secretKey = self::getSecretKey();

        if (empty($endpoint) || empty($secretKey) || empty($logs)) {
            $this->recordHealth(false, 'Tracker chưa được cấu hình.');
            return false;
        }

        $payload = apply_filters(HookNames::TRACKER_PAYLOAD, [
            'secret_key' => $secretKey,
            'site_url'   => get_bloginfo('url'),
            'logs'       => $logs,
        ], $logs);

        $eventType = sanitize_key((string) ($logs[0]['type'] ?? 'other'));

        if (self::hasTrackerEventTable()) {
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

        $result = $this->postPayload($payload, $blocking);
        $this->recordHealth($result['success'], $result['error'] ?? '', $result['code'] ?? null);

        return $result['success'];
    }

    private function postPayload(array $payload, bool $blocking = true): array
    {
        return TrackerHttpTransport::post(self::getEndpoint(), $payload, $blocking);
    }

    private function deliverStoredEvent(array $event, bool $blocking = true): bool
    {
        $payload = TrackerEventTable::decodeJsonColumn($event['payload'] ?? '');
        if (empty($payload)) {
            TrackerEventTable::markFailed((int) $event['id'], (int) ($event['attempts'] ?? 0), 'Payload cục bộ không hợp lệ.');
            $this->recordHealth(false, 'Payload cục bộ không hợp lệ.');
            return false;
        }

        $attempts = (int) ($event['attempts'] ?? 0) + 1;
        $result = $this->postPayload($payload, $blocking);

        if ($result['success']) {
            TrackerEventTable::markDelivered((int) $event['id'], $attempts);
            $this->recordHealth(true, '', $result['code'] ?? null);
            return true;
        }

        $error = $result['error'] ?: 'Không gửi được tracker event.';
        if (TrackerQueuePolicy::shouldFailPermanently($attempts, self::MAX_ATTEMPTS)) {
            TrackerEventTable::markFailed((int) $event['id'], $attempts, $error);
        } else {
            TrackerEventTable::markRetry((int) $event['id'], $attempts, $error, $this->nextAttemptAt($attempts));
        }

        $this->recordHealth(false, $error, $result['code'] ?? null);

        return false;
    }

    public function processQueue(): void
    {
        if (!self::isConfigured() || !self::hasTrackerEventTable()) {
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

        wp_schedule_single_event(time() + 60, self::CRON_RETRY);
        set_transient('_laca_tracker_retry_soon_scheduled', 1, 90);
    }

    public function sendHeartbeat(): void
    {
        $updateCounts = $this->getPendingUpdateCounts();

        $this->sendLogs([[
            'type'    => 'heartbeat',
            'content' => 'Client heartbeat: ' . home_url('/'),
            'level'   => 'info',
            'meta'    => [
                'site_url'     => home_url('/'),
                'site_name'    => get_bloginfo('name'),
                'wp_version'   => get_bloginfo('version'),
                'php_version'  => PHP_VERSION,
                'theme'        => get_stylesheet(),
                'parent_theme' => get_template(),
                'is_ssl'       => is_ssl(),
                'tracker_health' => self::getHealthSummary(),
                'updates_pending' => $updateCounts,
            ],
        ]], false, 'heartbeat');
    }

    public function sendMaintenanceSummary(): void
    {
        $health = self::getHealthSummary();
        $updateCounts = $this->getPendingUpdateCounts();
        $remoteHistory = array_values(array_filter(
            self::getRemoteUpdateHistory(),
            static fn($item): bool => is_array($item)
                && !empty($item['time'])
                && strtotime((string) $item['time']) >= current_time('timestamp') - WEEK_IN_SECONDS
        ));
        $remoteStatusCounts = TrackerHealthSummary::countRemoteHistoryStatuses($remoteHistory);
        $blockDiagnostics = class_exists(BlockSyncReceiver::class) ? BlockSyncReceiver::getDiagnostics() : [];
        $blockDiagnosticCounts = TrackerHealthSummary::countBlockDiagnostics($blockDiagnostics);
        $eventSummary = self::hasTrackerEventTable()
            ? TrackerEventTable::getSummarySince(7)
            : [];

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
            'type'    => 'maintenance_summary',
            'content' => implode("\n", $lines),
            'level'   => ((int) $health['failed'] > 0 || (int) $health['retry'] > 0) ? 'warning' : 'info',
            'meta'    => [
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
        ]], false, 'summary', [
            'period_days' => 7,
        ]);
    }

    private function getPendingUpdateCounts(): array
    {
        $pluginUpdates = get_site_transient('update_plugins');
        $themeUpdates = get_site_transient('update_themes');
        $coreUpdates = get_site_transient('update_core');
        $coreCount = 0;

        if (!empty($coreUpdates->updates) && is_array($coreUpdates->updates)) {
            foreach ($coreUpdates->updates as $update) {
                if (($update->response ?? '') === 'upgrade') {
                    $coreCount = 1;
                    break;
                }
            }
        }

        return [
            'plugins' => !empty($pluginUpdates->response) && is_array($pluginUpdates->response) ? count($pluginUpdates->response) : 0,
            'themes' => !empty($themeUpdates->response) && is_array($themeUpdates->response) ? count($themeUpdates->response) : 0,
            'core' => $coreCount,
        ];
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
        $health = get_option(self::OPT_HEALTH, []);
        if (!is_array($health)) {
            $health = [];
        }

        $health['configured'] = self::isConfigured();
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

        update_option(self::OPT_HEALTH, $health, false);
    }

    public static function getHealthSummary(): array
    {
        $health = get_option(self::OPT_HEALTH, []);
        if (!is_array($health)) {
            $health = [];
        }

        $queueCounts = [
            'queued' => 0,
            'retry' => 0,
            'failed' => 0,
            'delivered' => 0,
        ];

        if (self::hasTrackerEventTable()) {
            foreach (array_keys($queueCounts) as $status) {
                $queueCounts[$status] = TrackerEventTable::countByStatus($status);
            }
        }

        return TrackerHealthSummary::build($health, self::isConfigured(), $queueCounts);
    }

    // =========================================================================
    // STATIC CONFIG HELPERS
    // =========================================================================

    public static function getEndpoint(): string
    {
        return TrackerClientConfig::endpoint();
    }

    public static function getSecretKey(): string
    {
        return TrackerClientConfig::secretKey();
    }

    public static function isConfigured(): bool
    {
        return TrackerClientConfig::isConfigured();
    }

    /**
     * Đăng ký hooks — gọi từ hooks.php
     */
    public static function register(): void
    {
        new self();
    }

    // =========================================================================
    // CLIENT REQUEST — Public support/request intake for customer sites
    // =========================================================================

    /**
     * Đăng ký REST endpoint để form trên website khách gửi yêu cầu về lacadev.
     */
    public function registerClientRequestEndpoint(): void
    {
        register_rest_route('laca/v1', '/client/request', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleClientRequest'],
            'permission_callback' => '__return_true',
            'args'                => [
                'request_type' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'message' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'contact_name' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'contact_email' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_email',
                ],
                'page_url' => [
                    'required'          => false,
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);
    }

    /**
     * Nhận yêu cầu từ website khách và chuyển về tracker trung tâm.
     */
    public function handleClientRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        return TrackerClientRequestHandler::handle(
            $request,
            [self::class, 'isConfigured'],
            [self::class, 'hasTrackerEventTable'],
            fn(array $logs, bool $blocking, string $channel, array $context): bool => $this->sendLogs($logs, $blocking, $channel, $context)
        );
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
        $items = $this->getMaintenanceTimelineItems($limit);
        $extraClass = sanitize_html_class((string) $atts['class']);

        return TrackerShortcodeRenderer::maintenanceTimeline((string) $atts['title'], $extraClass, $items);
    }

    private function getMaintenanceTimelineItems(int $limit): array
    {
        return TrackerMaintenanceTimelineBuilder::build(
            self::getRemoteUpdateHistory(),
            (array) get_option('laca_block_activity_log', []),
            self::hasTrackerEventTable() ? TrackerEventTable::getRecent(80) : [],
            $limit
        );
    }

    // =========================================================================
    // REMOTE UPDATE — Nhận lệnh cập nhật từ xa từ lacadev.com
    // =========================================================================

    /**
     * Đăng ký REST endpoint /wp-json/laca/v1/remote-update
     * Nhận lệnh update plugin / theme / core từ lacadev.com
     */
    public function registerRemoteUpdateEndpoint(): void
    {
        register_rest_route('laca/v1', '/remote-update', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleRemoteUpdate'],
            'permission_callback' => '__return_true', // Auth qua secret key bên trong
        ]);
    }

    /**
     * Xử lý lệnh update đến từ lacadev.com
     *
     * Body JSON: { secret_key, action, slug? }
     *   action: update_plugin | update_theme | update_core
     *   slug:   file/folder của plugin hoặc theme (bỏ qua khi update_core)
     */
    public function handleRemoteUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $validated = RemoteUpdateRequestValidator::validate(
            $request->get_json_params() ?: [],
            self::getSecretKey()
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

        // 3) Load các class WordPress cần thiết
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $preflight = $this->preflightRemoteUpdate($action, $slug);
        if (!empty($validated['dry_run'])) {
            return new \WP_REST_Response([
                'success' => !empty($preflight['ok']),
                'message' => !empty($preflight['ok']) ? 'Preflight hoàn tất.' : 'Preflight không đạt.',
                'preflight' => $preflight,
                'snapshot' => $this->captureMaintenanceSnapshot($action, $slug),
            ], !empty($preflight['ok']) ? 200 : 400);
        }

        if (!empty($preflight['skip'])) {
            $msg = (string) ($preflight['message'] ?? 'Không có cập nhật cần chạy.');
            $this->recordMaintenanceEvent($action, $slug, 'skipped', $msg, [
                'preflight' => $preflight,
                'snapshot_before' => $this->captureMaintenanceSnapshot($action, $slug),
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
                'snapshot_before' => $this->captureMaintenanceSnapshot($action, $slug),
            ]);

            return new \WP_REST_Response([
                'success' => false,
                'message' => $msg,
                'preflight' => $preflight,
            ], 400);
        }

        // Dùng Automatic_Upgrader_Skin để không output HTML
        $skin = new \Automatic_Upgrader_Skin();
        $snapshotBefore = $this->captureMaintenanceSnapshot($action, $slug);
        $useMaintenance = $this->shouldUseTemporaryMaintenance($action, $params);
        $maintenanceOwner = 'remote_update_' . md5($action . '|' . $slug . '|' . microtime(true));
        $temporaryMaintenanceEnabled = $useMaintenance
            ? MaintenanceModeManager::activateTemporary($maintenanceOwner, 30 * MINUTE_IN_SECONDS)
            : false;

        // 4) Thực thi theo action
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
                $this->captureMaintenanceSnapshot($action, $slug),
                $temporaryMaintenanceEnabled
            );
            $this->recordMaintenanceEvent($action, $slug, 'skipped', $msg, $meta);
            MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
            delete_transient('_laca_remote_update_in_progress');

            return new \WP_REST_Response([
                'success' => true,
                'message' => $msg,
            ]);
        }

        $result = $execution['result'] ?? null;
        $label = (string) ($execution['label'] ?? $action);

        // 5) Xử lý kết quả
        if (is_wp_error($result)) {
            $msg = "Cập nhật {$label} thất bại: " . $result->get_error_message();
            $meta = RemoteUpdateMeta::build(
                $preflight,
                $snapshotBefore,
                $this->captureMaintenanceSnapshot($action, $slug),
                $temporaryMaintenanceEnabled,
                $this->rollbackNote($action, $slug)
            );
            $this->recordMaintenanceEvent($action, $slug, 'failed', $msg, $meta);
            $this->sendLogs([['type' => 'other', 'content' => $msg, 'level' => 'critical', 'meta' => $meta]]);
            MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
            delete_transient('_laca_remote_update_in_progress');
            return new \WP_REST_Response(['success' => false, 'message' => $msg], 500);
        }

        if ($result === false || $result === null) {
            $msg = "Cập nhật {$label} không thành công (có thể đã ở phiên bản mới nhất).";
            $meta = RemoteUpdateMeta::build(
                $preflight,
                $snapshotBefore,
                $this->captureMaintenanceSnapshot($action, $slug),
                $temporaryMaintenanceEnabled,
                $this->rollbackNote($action, $slug)
            );
            $this->recordMaintenanceEvent($action, $slug, 'skipped', $msg, $meta);
            $this->sendLogs([['type' => 'other', 'content' => $msg, 'level' => 'warning', 'meta' => $meta]]);
            MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
            delete_transient('_laca_remote_update_in_progress');
            return new \WP_REST_Response(['success' => false, 'message' => $msg]);
        }

        // Thành công — ghi log về lacadev
        $successMsg = "✅ Đã cập nhật {$label} thành công từ lệnh remote.";
        $meta = RemoteUpdateMeta::build(
            $preflight,
            $snapshotBefore,
            $this->captureMaintenanceSnapshot($action, $slug),
            $temporaryMaintenanceEnabled
        );
        $this->recordMaintenanceEvent($action, $slug, 'success', $successMsg, $meta);
        $this->sendLogs([['type' => 'deployment', 'content' => $successMsg, 'level' => 'info', 'meta' => $meta]]);
        MaintenanceModeManager::deactivateTemporary($maintenanceOwner);
        delete_transient('_laca_remote_update_in_progress');

        return new \WP_REST_Response([
            'success' => true,
            'message' => $successMsg,
        ]);
    }

    private function preflightRemoteUpdate(string $action, string $slug): array
    {
        return RemoteUpdatePreflight::check($action, $slug);
    }

    private function captureMaintenanceSnapshot(string $action, string $slug): array
    {
        return MaintenanceSnapshot::capture($action, $slug);
    }

    private function shouldUseTemporaryMaintenance(string $action, array $params): bool
    {
        return RemoteUpdatePolicy::shouldUseTemporaryMaintenance($action, $params);
    }

    private function rollbackNote(string $action, string $slug): string
    {
        return RemoteUpdatePolicy::rollbackNote($action, $slug);
    }

    private function recordMaintenanceEvent(string $action, string $slug, string $status, string $message, array $meta = []): void
    {
        update_option(
            self::OPT_REMOTE_HISTORY,
            RemoteUpdateHistoryStore::append(
                get_option(self::OPT_REMOTE_HISTORY, []),
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

    public static function getRemoteUpdateHistory(): array
    {
        return RemoteUpdateHistory::normalize(get_option(self::OPT_REMOTE_HISTORY, []));
    }
}
