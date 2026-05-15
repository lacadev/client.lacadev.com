<?php

namespace App\Settings\Tracker;

/**
 * Decision helpers for remote update requests.
 */
final class RemoteUpdatePolicy
{
    public static function shouldUseTemporaryMaintenance(string $action, array $params): bool
    {
        if (array_key_exists('maintenance_mode', $params)) {
            return (bool) $params['maintenance_mode'];
        }

        return in_array($action, ['update_theme', 'update_core'], true);
    }

    public static function rollbackNote(string $action, string $slug): string
    {
        return match ($action) {
            'update_plugin' => "Kiểm tra plugin {$slug}, rollback bằng bản backup/plugin zip nếu website lỗi.",
            'update_theme' => "Kiểm tra theme {$slug}, rollback bằng bản backup/theme zip nếu giao diện lỗi.",
            'update_core' => 'Kiểm tra WordPress core, restore backup nếu update làm site lỗi.',
            default => 'Kiểm tra snapshot trước/sau và restore backup nếu cần.',
        };
    }
}
