<?php

namespace App\Settings;

use App\Contracts\HookNames;
use App\Databases\TrackerEventTable;
use App\Settings\Tracker\Client\DeliveryManager;
use App\Settings\Tracker\Client\DigestRunner;
use App\Settings\Tracker\Client\EventMonitor;
use App\Settings\Tracker\Client\RemoteUpdateController;
use App\Settings\Tracker\Client\ScheduleManager;
use App\Settings\Tracker\MaintenanceSnapshot;
use App\Settings\Tracker\RemoteUpdateHistory;
use App\Settings\Tracker\TrackerClientConfig;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LacaDev Tracker Client
 *
 * Facade đăng ký hook/cron/REST cho tracker phía client.
 * Logic nghiệp vụ được tách sang các service con theo từng trách nhiệm.
 */
class LacaDevTrackerClient
{
    const CF_ENDPOINT = 'laca_tracker_endpoint';
    const CF_SECRET = 'laca_tracker_secret_key';

    const CRON_HOURLY = 'laca_tracker_hourly_scan';
    const CRON_DAILY = 'laca_tracker_daily_digest';
    const CRON_RETRY = 'laca_tracker_retry_queue';
    const CRON_HEARTBEAT = 'laca_tracker_heartbeat';
    const CRON_WEEKLY_SUMMARY = 'laca_tracker_weekly_summary';

    const OPT_HEALTH = '_laca_tracker_health';
    const OPT_REMOTE_HISTORY = '_laca_remote_update_history';
    const OPT_TABLE_INSTALL_CHECK = '_laca_tracker_events_install_checked';
    const MAX_ATTEMPTS = 5;

    /**
     * @var array<int, string>
     */
    const SUSPICIOUS_DIRS = [
        '',
        'wp-content/uploads',
        'wp-content/mu-plugins',
    ];

    const OPT_BASELINE = '_laca_tracker_file_baseline';
    const OPT_KNOWN_UPDATES = '_laca_tracker_known_plugin_updates';

    private ScheduleManager $scheduleManager;
    private EventMonitor $eventMonitor;
    private DigestRunner $digestRunner;
    private DeliveryManager $deliveryManager;
    private RemoteUpdateController $remoteUpdateController;

    public function __construct()
    {
        $this->scheduleManager = new ScheduleManager();
        $this->deliveryManager = self::newDeliveryManager();
        $sendLogs = function (array $logs, bool $blocking = false, string $channel = 'tracker', array $context = []): bool {
            return $this->sendLogs($logs, $blocking, $channel, $context);
        };

        $this->eventMonitor = new EventMonitor($sendLogs);
        $this->digestRunner = new DigestRunner($sendLogs);
        $this->remoteUpdateController = new RemoteUpdateController(
            $sendLogs,
            [self::class, 'hasTrackerEventTable']
        );

        add_filter('cron_schedules', [$this, 'addCronSchedules']);

        add_action('upgrader_process_complete', [$this, 'onUpgraderComplete'], 20, 2);
        add_action('delete_plugin', [$this, 'onDeletePlugin']);
        add_action('deleted_plugin', [$this, 'afterDeletePlugin'], 10, 2);
        add_action('activated_plugin', [$this, 'onActivatePlugin']);
        add_action('deactivated_plugin', [$this, 'onDeactivatePlugin']);
        add_action(HookNames::BLOCK_SYNC_RECEIVED, [$this, 'onBlockSyncReceived'], 10, 3);
        add_filter('set_site_transient_update_plugins', [$this, 'onUpdateTransientSet']);

        add_action('rest_api_init', [$this, 'registerRemoteUpdateEndpoint']);
        add_action('rest_api_init', [$this, 'registerClientRequestEndpoint']);

        add_action(self::CRON_HOURLY, [$this, 'runHourlyScan']);
        if (!wp_next_scheduled(self::CRON_HOURLY)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOURLY);
        }

        add_action(self::CRON_DAILY, [$this, 'runDailyDigest']);
        if (!wp_next_scheduled(self::CRON_DAILY)) {
            wp_schedule_event(strtotime('tomorrow 01:00:00 UTC'), 'daily', self::CRON_DAILY);
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
        return $this->scheduleManager->addCronSchedules($schedules);
    }

    private function nextWeeklySummaryRun(): int
    {
        return $this->scheduleManager->nextWeeklySummaryRun();
    }

    private function ensureRecurringEvent(string $hook, string $schedule, int $timestamp): void
    {
        $this->scheduleManager->ensureRecurringEvent($hook, $schedule, $timestamp);
    }

    public function onUpdateTransientSet(mixed $value): mixed
    {
        return $this->eventMonitor->onUpdateTransientSet($value);
    }

    public function onUpgraderComplete(mixed $upgrader, array $options): void
    {
        $this->eventMonitor->onUpgraderComplete($upgrader, $options);
    }

    public function onDeletePlugin(string $pluginFile): void
    {
        $this->eventMonitor->onDeletePlugin($pluginFile);
    }

    public function afterDeletePlugin(string $pluginFile, bool $deleted): void
    {
        $this->eventMonitor->afterDeletePlugin($pluginFile, $deleted);
    }

    public function onActivatePlugin(string $pluginFile): void
    {
        $this->eventMonitor->onActivatePlugin($pluginFile);
    }

    public function onDeactivatePlugin(string $pluginFile): void
    {
        $this->eventMonitor->onDeactivatePlugin($pluginFile);
    }

    public function onBlockSyncReceived(string $blockName, string $version, bool $isUpdate): void
    {
        $this->eventMonitor->onBlockSyncReceived($blockName, $version, $isUpdate);
    }

    public function runHourlyScan(): void
    {
        $this->digestRunner->runHourlyScan(self::SUSPICIOUS_DIRS);
    }

    public function runDailyDigest(): void
    {
        $this->digestRunner->runDailyDigest();
    }

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
     * @param array<int, array<string, mixed>> $logs
     * @param array<string, mixed> $context
     */
    private function sendLogs(array $logs, bool $blocking = false, string $channel = 'tracker', array $context = []): bool
    {
        return $this->deliveryManager->sendLogs($logs, $blocking, $channel, $context);
    }

    public function processQueue(): void
    {
        $this->deliveryManager->processQueue();
    }

    public function sendHeartbeat(): void
    {
        $this->deliveryManager->sendHeartbeat($this->getPendingUpdateCounts());
    }

    public function sendMaintenanceSummary(): void
    {
        $this->deliveryManager->sendMaintenanceSummary($this->getPendingUpdateCounts());
    }

    /**
     * @return array<string, int>
     */
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

    /**
     * @return array<string, mixed>
     */
    public static function getHealthSummary(): array
    {
        return self::newDeliveryManager()->getHealthSummary();
    }

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

    public static function register(): void
    {
        new self();
    }

    public function registerClientRequestEndpoint(): void
    {
        $this->remoteUpdateController->registerClientRequestEndpoint();
    }

    public function handleClientRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->remoteUpdateController->handleClientRequest($request);
    }

    public function renderSupportCenterShortcode(array $atts = []): string
    {
        return $this->deliveryManager->renderSupportCenterShortcode($atts);
    }

    public function renderMaintenanceTimelineShortcode(array $atts = []): string
    {
        return $this->deliveryManager->renderMaintenanceTimelineShortcode($atts);
    }

    public function registerRemoteUpdateEndpoint(): void
    {
        $this->remoteUpdateController->registerRemoteUpdateEndpoint();
    }

    public function handleRemoteUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        return $this->remoteUpdateController->handleRemoteUpdate($request);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getRemoteUpdateHistory(): array
    {
        return RemoteUpdateHistory::normalize(get_option(self::OPT_REMOTE_HISTORY, []));
    }

    private static function newDeliveryManager(): DeliveryManager
    {
        return new DeliveryManager(
            [self::class, 'hasTrackerEventTable'],
            [self::class, 'getRemoteUpdateHistory'],
            static fn(string $action, string $slug): array => MaintenanceSnapshot::capture($action, $slug)
        );
    }
}
