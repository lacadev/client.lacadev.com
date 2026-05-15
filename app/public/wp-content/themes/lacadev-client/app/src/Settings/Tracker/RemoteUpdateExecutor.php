<?php

namespace App\Settings\Tracker;

/**
 * Executes remote update commands against WordPress upgrader APIs.
 */
final class RemoteUpdateExecutor
{
    public static function execute(string $action, string $slug, object $skin): array
    {
        switch ($action) {
            case 'update_plugin':
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                wp_update_plugins();

                return [
                    'label' => "plugin '{$slug}'",
                    'result' => (new \Plugin_Upgrader($skin))->upgrade($slug),
                ];

            case 'update_theme':
                wp_update_themes();

                return [
                    'label' => "theme '{$slug}'",
                    'result' => (new \Theme_Upgrader($skin))->upgrade($slug),
                ];

            case 'update_core':
                require_once ABSPATH . 'wp-admin/includes/update.php';
                $updates = get_core_updates();

                if (empty($updates) || !isset($updates[0]->response) || $updates[0]->response === 'latest') {
                    return [
                        'skip' => true,
                        'message' => 'WordPress đã ở phiên bản mới nhất, không cần cập nhật.',
                    ];
                }

                return [
                    'label' => 'WordPress core',
                    'result' => (new \Core_Upgrader($skin))->upgrade($updates[0]),
                ];

            default:
                return [
                    'invalid' => true,
                    'message' => 'Action không hợp lệ.',
                ];
        }
    }
}
