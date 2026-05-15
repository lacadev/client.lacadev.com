<?php

namespace App\Settings\Tracker;

use App\Settings\MaintenanceModeManager;

/**
 * Captures pre/post remote-maintenance state.
 */
final class MaintenanceSnapshot
{
    public static function capture(string $action, string $slug): array
    {
        $snapshot = [
            'time' => current_time('mysql'),
            'site_url' => home_url('/'),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'stylesheet' => get_stylesheet(),
            'template' => get_template(),
            'maintenance_active' => (bool) get_option(MaintenanceModeManager::OPT_ACTIVE),
            'active_plugins_count' => count((array) get_option('active_plugins', [])),
            'target' => [],
        ];

        if ($action === 'update_plugin' && $slug !== '' && file_exists(WP_PLUGIN_DIR . '/' . $slug)) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $slug, false, false);
            $snapshot['target'] = [
                'type' => 'plugin',
                'slug' => $slug,
                'name' => (string) ($data['Name'] ?? $slug),
                'version' => (string) ($data['Version'] ?? ''),
                'active' => function_exists('is_plugin_active') ? is_plugin_active($slug) : null,
            ];
        } elseif ($action === 'update_theme' && $slug !== '') {
            $theme = wp_get_theme($slug);
            $snapshot['target'] = [
                'type' => 'theme',
                'slug' => $slug,
                'name' => $theme->exists() ? ($theme->get('Name') ?: $slug) : $slug,
                'version' => $theme->exists() ? ($theme->get('Version') ?: '') : '',
                'active' => get_stylesheet() === $slug || get_template() === $slug,
            ];
        } elseif ($action === 'update_core') {
            $snapshot['target'] = [
                'type' => 'core',
                'version' => get_bloginfo('version'),
            ];
        }

        return $snapshot;
    }
}
