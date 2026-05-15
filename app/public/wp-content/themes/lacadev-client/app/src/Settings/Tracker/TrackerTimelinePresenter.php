<?php

namespace App\Settings\Tracker;

/**
 * Presentation helpers for the public maintenance timeline.
 */
final class TrackerTimelinePresenter
{
    public static function makeItem(string $time, string $title, string $message, string $status): array
    {
        return [
            'time' => $time,
            'title' => $title,
            'message' => wp_trim_words(wp_strip_all_tags($message), 34),
            'tone' => self::tone($status),
            'status_label' => self::statusLabel($status),
        ];
    }

    public static function publicMessage(array $log, array $event): string
    {
        $type = sanitize_key((string) ($log['type'] ?? $event['event_type'] ?? 'other'));

        if ($type === 'client_request') {
            $requestId = (string) ($log['request_id'] ?? '');
            return $requestId !== ''
                ? sprintf(__('Yêu cầu hỗ trợ %s đã được ghi nhận.', 'laca'), $requestId)
                : __('Yêu cầu hỗ trợ đã được ghi nhận.', 'laca');
        }

        if (in_array($type, ['file_changed', 'file_suspicious', 'code_edit'], true)) {
            return __('Hệ thống đã ghi nhận cảnh báo kỹ thuật để đội LacaDev kiểm tra.', 'laca');
        }

        if ($type === 'update_pending') {
            return __('Hệ thống đã ghi nhận các bản cập nhật đang chờ xử lý.', 'laca');
        }

        if ($type === 'maintenance_summary') {
            return __('Báo cáo chăm sóc định kỳ đã được tạo.', 'laca');
        }

        if (in_array($type, self::allowedFullMessageTypes(), true)) {
            return (string) ($log['content'] ?? '');
        }

        return '';
    }

    public static function typeLabel(string $type): string
    {
        return [
            'deployment' => __('Triển khai/cập nhật', 'laca'),
            'plugin_update' => __('Cập nhật plugin', 'laca'),
            'theme_update' => __('Cập nhật theme', 'laca'),
            'core_update' => __('Cập nhật WordPress', 'laca'),
            'plugin_install' => __('Cài plugin', 'laca'),
            'plugin_activate' => __('Kích hoạt plugin', 'laca'),
            'plugin_deactivate' => __('Tắt plugin', 'laca'),
            'plugin_delete' => __('Xóa plugin', 'laca'),
            'block_sync' => __('Cập nhật block giao diện', 'laca'),
            'client_request' => __('Yêu cầu hỗ trợ', 'laca'),
            'update_pending' => __('Theo dõi cập nhật', 'laca'),
            'maintenance_summary' => __('Báo cáo định kỳ', 'laca'),
        ][$type] ?? __('Hoạt động bảo trì', 'laca');
    }

    public static function maintenanceActionLabel(string $action): string
    {
        return [
            'update_plugin' => __('Cập nhật plugin từ xa', 'laca'),
            'update_theme' => __('Cập nhật theme từ xa', 'laca'),
            'update_core' => __('Cập nhật WordPress từ xa', 'laca'),
        ][$action] ?? __('Bảo trì website', 'laca');
    }

    public static function formatDate(string $time): string
    {
        $timestamp = strtotime($time);

        return $timestamp ? date_i18n('d/m/Y H:i', $timestamp) : $time;
    }

    private static function tone(string $status): string
    {
        return match ($status) {
            'success', 'delivered' => 'done',
            'failed' => 'attention',
            'queued', 'retry' => 'pending',
            default => 'neutral',
        };
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'success', 'delivered' => __('Hoàn tất', 'laca'),
            'failed' => __('Cần kiểm tra', 'laca'),
            'queued', 'retry' => __('Đang xử lý', 'laca'),
            'skipped' => __('Bỏ qua', 'laca'),
            default => __('Đã ghi nhận', 'laca'),
        };
    }

    private static function allowedFullMessageTypes(): array
    {
        return [
            'deployment',
            'plugin_update',
            'theme_update',
            'core_update',
            'plugin_install',
            'plugin_activate',
            'plugin_deactivate',
            'plugin_delete',
            'block_sync',
        ];
    }
}
