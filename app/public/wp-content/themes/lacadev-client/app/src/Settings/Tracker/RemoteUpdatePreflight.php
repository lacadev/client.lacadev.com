<?php

namespace App\Settings\Tracker;

/**
 * WordPress-specific preflight checks for remote update commands.
 */
final class RemoteUpdatePreflight
{
    public static function check(string $action, string $slug): array
    {
        $errors = [];
        $warnings = [];
        $target = [];

        if (function_exists('wp_is_file_mod_allowed') && !wp_is_file_mod_allowed('automatic_updater')) {
            $errors[] = 'WordPress đang chặn chỉnh sửa file tự động.';
        }

        if (!defined('WP_CONTENT_DIR') || !is_dir(WP_CONTENT_DIR)) {
            $errors[] = 'Không xác định được thư mục wp-content.';
        } elseif (!is_writable(WP_CONTENT_DIR)) {
            $warnings[] = 'wp-content có thể không ghi được; updater vẫn có thể dùng filesystem credentials nếu server hỗ trợ.';
        }

        if ($action === 'update_plugin') {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $pluginPath = WP_PLUGIN_DIR . '/' . $slug;
            if (!file_exists($pluginPath)) {
                $errors[] = 'Không tìm thấy plugin target.';
            } else {
                $pluginData = get_plugin_data($pluginPath, false, false);
                wp_update_plugins();
                $updates = get_site_transient('update_plugins');
                $newVersion = !empty($updates->response[$slug]->new_version) ? (string) $updates->response[$slug]->new_version : '';
                if ($newVersion === '') {
                    $warnings[] = 'Không thấy bản cập nhật pending trong WordPress transient; updater có thể trả về skipped.';
                }

                $target = [
                    'type' => 'plugin',
                    'name' => (string) ($pluginData['Name'] ?? $slug),
                    'current_version' => (string) ($pluginData['Version'] ?? ''),
                    'new_version' => $newVersion,
                    'active' => function_exists('is_plugin_active') ? is_plugin_active($slug) : null,
                ];
            }
        } elseif ($action === 'update_theme') {
            wp_update_themes();
            $theme = wp_get_theme($slug);
            if (!$theme->exists()) {
                $errors[] = 'Không tìm thấy theme target.';
            } else {
                $updates = get_site_transient('update_themes');
                $newVersion = !empty($updates->response[$slug]['new_version']) ? (string) $updates->response[$slug]['new_version'] : '';
                if ($newVersion === '') {
                    $warnings[] = 'Không thấy bản cập nhật pending trong WordPress transient; updater có thể trả về skipped.';
                }

                $target = [
                    'type' => 'theme',
                    'name' => $theme->get('Name') ?: $slug,
                    'current_version' => $theme->get('Version') ?: '',
                    'new_version' => $newVersion,
                    'active' => get_stylesheet() === $slug || get_template() === $slug,
                ];
            }
        } elseif ($action === 'update_core') {
            require_once ABSPATH . 'wp-admin/includes/update.php';
            wp_version_check();
            $updates = get_core_updates();
            $next = $updates[0] ?? null;
            $target = [
                'type' => 'core',
                'current_version' => get_bloginfo('version'),
                'new_version' => is_object($next) ? (string) ($next->version ?? '') : '',
            ];

            if (empty($updates) || !is_object($next) || ($next->response ?? '') === 'latest') {
                return [
                    'ok' => true,
                    'skip' => true,
                    'message' => 'WordPress đã ở phiên bản mới nhất, không cần cập nhật.',
                    'warnings' => $warnings,
                    'target' => $target,
                ];
            }
        }

        return self::result($errors, $warnings, $target);
    }

    public static function result(array $errors, array $warnings = [], array $target = []): array
    {
        return [
            'ok' => empty($errors),
            'skip' => false,
            'errors' => $errors,
            'warnings' => $warnings,
            'target' => $target,
        ];
    }
}
