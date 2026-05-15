<?php

declare(strict_types=1);

use App\Settings\Tracker\RemoteUpdatePolicy;
use App\Settings\Tracker\TrackerTimelinePresenter;

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words(string $text, int $numWords): string
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        return implode(' ', array_slice($words, 0, $numWords));
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, int $timestamp): string
    {
        return date($format, $timestamp);
    }
}

test('TrackerTimelinePresenter builds public timeline items with tone and labels', function (): void {
    $item = TrackerTimelinePresenter::makeItem(
        '2026-05-15 10:00:00',
        'Update',
        '<strong>Plugin updated successfully</strong>',
        'success'
    );

    assert_same('done', $item['tone']);
    assert_same('Hoàn tất', $item['status_label']);
    assert_same('Plugin updated successfully', $item['message']);

    $failed = TrackerTimelinePresenter::makeItem('now', 'Scan', 'Bad file', 'failed');
    assert_same('attention', $failed['tone']);
});

test('TrackerTimelinePresenter maps tracker log types to public-safe messages', function (): void {
    assert_same(
        'Yêu cầu hỗ trợ REQ-1 đã được ghi nhận.',
        TrackerTimelinePresenter::publicMessage([
            'type' => 'client_request',
            'request_id' => 'REQ-1',
        ], [])
    );

    assert_same(
        'Hệ thống đã ghi nhận cảnh báo kỹ thuật để đội LacaDev kiểm tra.',
        TrackerTimelinePresenter::publicMessage(['type' => 'file_suspicious'], [])
    );

    assert_same(
        'Plugin A updated',
        TrackerTimelinePresenter::publicMessage(['type' => 'plugin_update', 'content' => 'Plugin A updated'], [])
    );

    assert_same('', TrackerTimelinePresenter::publicMessage(['type' => 'private_debug'], []));
});

test('TrackerTimelinePresenter formats labels and dates', function (): void {
    assert_same('Cập nhật plugin', TrackerTimelinePresenter::typeLabel('plugin_update'));
    assert_same('Hoạt động bảo trì', TrackerTimelinePresenter::typeLabel('unknown'));
    assert_same('Cập nhật theme từ xa', TrackerTimelinePresenter::maintenanceActionLabel('update_theme'));
    assert_same('15/05/2026 10:00', TrackerTimelinePresenter::formatDate('2026-05-15 10:00:00'));
    assert_same('not-a-date', TrackerTimelinePresenter::formatDate('not-a-date'));
});

test('RemoteUpdatePolicy decides maintenance mode and rollback notes', function (): void {
    assert_true(RemoteUpdatePolicy::shouldUseTemporaryMaintenance('update_theme', []));
    assert_true(RemoteUpdatePolicy::shouldUseTemporaryMaintenance('update_core', []));
    assert_true(!RemoteUpdatePolicy::shouldUseTemporaryMaintenance('update_plugin', []));
    assert_true(!RemoteUpdatePolicy::shouldUseTemporaryMaintenance('update_theme', ['maintenance_mode' => false]));

    assert_same(
        'Kiểm tra plugin akismet/akismet.php, rollback bằng bản backup/plugin zip nếu website lỗi.',
        RemoteUpdatePolicy::rollbackNote('update_plugin', 'akismet/akismet.php')
    );
});
