<?php

namespace App\Settings\Tracker\Client;

use App\Settings\Tracker\SuspiciousFileScanner;

class DigestRunner
{
    /**
     * @var callable
     */
    private $sendLogs;

    public function __construct(callable $sendLogs)
    {
        $this->sendLogs = $sendLogs;
    }

    /**
     * @param array<int, string> $dirs
     */
    public function runHourlyScan(array $dirs): void
    {
        $found = SuspiciousFileScanner::scan(ABSPATH, $dirs);

        if (!empty($found)) {
            $list = implode("\n", array_map(fn($file) => '  - ' . $file, $found));
            ($this->sendLogs)([[
                'type'    => 'file_suspicious',
                'content' => "⚠️ Phát hiện file đáng ngờ:\n{$list}",
                'level'   => 'critical',
            ]]);
        }
    }

    public function runDailyDigest(): void
    {
        $logs = [];
        $pluginUpdates = $this->getPendingPluginUpdates();
        $knownKeys = (array) get_option(\App\Settings\LacaDevTrackerClient::OPT_KNOWN_UPDATES, []);
        $newPlugins = array_filter($pluginUpdates, static function (array $plugin) use ($knownKeys): bool {
            return !in_array($plugin['slug'] ?? '', $knownKeys, true);
        });

        if (!empty($newPlugins)) {
            $list = implode("\n", array_map(fn($plugin) => "  - {$plugin['name']}: {$plugin['current']} → {$plugin['new']}", $newPlugins));
            $logs[] = [
                'type'    => 'update_pending',
                'content' => 'Plugin mới chờ update (' . count($newPlugins) . "):\n{$list}",
                'level'   => 'warning',
            ];
        }

        $themeUpdates = $this->getPendingThemeUpdates();
        if (!empty($themeUpdates)) {
            $list = implode("\n", array_map(fn($theme) => "  - {$theme['name']}: {$theme['current']} → {$theme['new']}", $themeUpdates));
            $logs[] = [
                'type'    => 'update_pending',
                'content' => '🎨 Có ' . count($themeUpdates) . " theme chờ update:\n{$list}",
                'level'   => 'warning',
            ];
        }

        $coreUpdate = $this->getPendingCoreUpdate();
        if ($coreUpdate) {
            $logs[] = [
                'type'    => 'update_pending',
                'content' => "🔄 WordPress Core: {$coreUpdate['current']} → {$coreUpdate['new']} (có bản mới)",
                'level'   => 'warning',
            ];
        }

        $modifiedFiles = $this->checkFileIntegrity();
        if (!empty($modifiedFiles)) {
            $list = implode("\n", array_map(fn($file) => '  - ' . $file, $modifiedFiles));
            $logs[] = [
                'type'    => 'file_changed',
                'content' => "📝 Phát hiện file theme/plugin bị thay đổi:\n{$list}",
                'level'   => 'critical',
            ];
        }

        if (!empty($logs)) {
            ($this->sendLogs)($logs);
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getPendingPluginUpdates(): array
    {
        wp_update_plugins();

        $updates = get_site_transient('update_plugins');
        if (empty($updates->response)) {
            return [];
        }

        $result = [];
        foreach ($updates->response as $pluginFile => $data) {
            $installed = get_plugin_data(WP_PLUGIN_DIR . '/' . $pluginFile, false, false);
            $result[] = [
                'slug' => $pluginFile,
                'name' => $installed['Name'] ?? $pluginFile,
                'current' => $installed['Version'] ?? '?',
                'new' => $data->new_version ?? '?',
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, string>>
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
            $theme = wp_get_theme($themeSlug);
            $result[] = [
                'name' => $theme->get('Name') ?: $themeSlug,
                'current' => $theme->get('Version') ?: '?',
                'new' => $data['new_version'] ?? '?',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, string>|null
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
                    'new' => $update->version ?? '?',
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function checkFileIntegrity(): array
    {
        $baseline = get_option(\App\Settings\LacaDevTrackerClient::OPT_BASELINE, []);
        $comparison = \App\Settings\Tracker\TrackerFileIntegrity::compare(
            is_array($baseline) ? $baseline : [],
            $this->getIntegrityWatchPaths(),
            'file_exists',
            'filemtime',
            static fn(int $mtime): string => date('d/m/Y H:i', $mtime)
        );

        update_option(\App\Settings\LacaDevTrackerClient::OPT_BASELINE, $comparison['current'], false);

        return $comparison['changed'];
    }

    /**
     * @return array<string, string>
     */
    private function getIntegrityWatchPaths(): array
    {
        $paths = [];
        $activeTheme = get_stylesheet_directory();
        $themeSlug = get_stylesheet();
        $themeFiles = array_merge(
            glob($activeTheme . '/*.php') ?: [],
            glob($activeTheme . '/*.js') ?: [],
            glob($activeTheme . '/*.css') ?: [],
            glob($activeTheme . '/functions.php') ?: []
        );

        foreach (array_unique($themeFiles) as $file) {
            $paths[$file] = 'themes/' . $themeSlug . '/' . basename($file);
        }

        $parentTheme = get_template_directory();
        if ($parentTheme !== $activeTheme) {
            $parentFunctions = $parentTheme . '/functions.php';
            if (file_exists($parentFunctions)) {
                $paths[$parentFunctions] = 'themes/' . get_template() . '/functions.php';
            }
        }

        foreach ((array) get_option('active_plugins', []) as $pluginRel) {
            $pluginPath = WP_PLUGIN_DIR . '/' . $pluginRel;
            if (file_exists($pluginPath)) {
                $paths[$pluginPath] = 'plugins/' . $pluginRel;
            }
        }

        return $paths;
    }

    /**
     * @return array<string, int>
     */
    public function getPendingUpdateCounts(): array
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
}
