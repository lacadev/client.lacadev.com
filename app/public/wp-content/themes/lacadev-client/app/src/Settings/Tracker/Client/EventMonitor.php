<?php

namespace App\Settings\Tracker\Client;

use App\Settings\BlockSyncReceiver;

class EventMonitor
{
    /**
     * @var callable
     */
    private $sendLogs;

    public function __construct(callable $sendLogs)
    {
        $this->sendLogs = $sendLogs;
    }

    public function onUpdateTransientSet(mixed $value): mixed
    {
        if (empty($value->response) || !is_array($value->response)) {
            return $value;
        }

        $currentKeys = array_keys($value->response);
        sort($currentKeys);

        $knownKeys = (array) get_option(\App\Settings\LacaDevTrackerClient::OPT_KNOWN_UPDATES, []);
        sort($knownKeys);

        $newlyFound = array_diff($currentKeys, $knownKeys);

        if (!empty($newlyFound)) {
            $logs = [];
            foreach ($newlyFound as $pluginFile) {
                $data       = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
                $name       = $data['Name'] ?? $pluginFile;
                $current    = $data['Version'] ?? '?';
                $newVersion = $value->response[$pluginFile]->new_version ?? '?';

                $logs[] = [
                    'type'    => 'update_pending',
                    'content' => "⚠️ Plugin cần update: {$name}\n  Phiên bản hiện tại: {$current} → Bản mới: {$newVersion}",
                    'level'   => 'warning',
                ];
            }

            if (!empty($logs)) {
                ($this->sendLogs)($logs);
            }
        }

        update_option(\App\Settings\LacaDevTrackerClient::OPT_KNOWN_UPDATES, $currentKeys, false);

        return $value;
    }

    public function onUpgraderComplete(mixed $upgrader, array $options): void
    {
        $action = $options['action'] ?? '';
        $type   = $options['type'] ?? '';

        if ($action !== 'update' && $action !== 'install') {
            return;
        }

        $logs = [];

        if ($type === 'plugin') {
            $plugins = (array) ($options['plugins'] ?? []);
            if ($action === 'install' && !empty($upgrader->new_plugin_data)) {
                $name    = $upgrader->new_plugin_data['Name'] ?? 'Không rõ';
                $version = $upgrader->new_plugin_data['Version'] ?? '';
                $logs[]  = [
                    'type'    => 'plugin_install',
                    'content' => "Cài mới plugin: {$name}" . ($version ? " v{$version}" : ''),
                    'level'   => 'info',
                ];
            } else {
                foreach ($plugins as $plugin) {
                    $data    = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
                    $name    = $data['Name'] ?? $plugin;
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
                $name    = $data->get('Name') ?: $theme;
                $version = $data->get('Version') ?: '';
                $logs[]  = [
                    'type'    => 'theme_update',
                    'content' => "Cập nhật theme: {$name}" . ($version ? " → v{$version}" : ''),
                    'level'   => 'info',
                ];
            }
        } elseif ($type === 'core') {
            $logs[] = [
                'type'    => 'core_update',
                'content' => 'Cập nhật WordPress Core → v' . get_bloginfo('version'),
                'level'   => 'info',
            ];
        }

        if (!empty($logs)) {
            delete_option(\App\Settings\LacaDevTrackerClient::OPT_BASELINE);
            ($this->sendLogs)($logs);
        }
    }

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

        ($this->sendLogs)([[
            'type'    => 'plugin_delete',
            'content' => "⚠️ Đã xóa plugin: {$name}",
            'level'   => 'warning',
        ]]);
    }

    public function onActivatePlugin(string $pluginFile): void
    {
        $data    = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        $name    = $data['Name'] ?? $pluginFile;
        $version = $data['Version'] ?? '';

        ($this->sendLogs)([[
            'type'    => 'plugin_activate',
            'content' => "✅ Kích hoạt plugin: {$name}" . ($version ? " v{$version}" : ''),
            'level'   => 'info',
        ]]);
    }

    public function onDeactivatePlugin(string $pluginFile): void
    {
        $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
        $name = $data['Name'] ?? $pluginFile;

        ($this->sendLogs)([[
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

        ($this->sendLogs)([[
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
}
