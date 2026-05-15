<?php

declare(strict_types=1);

use App\Settings\Tracker\TrackerMaintenanceTimelineBuilder;

if (!function_exists('sanitize_key')) {
    function sanitize_key(mixed $value): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }
}

test('TrackerMaintenanceTimelineBuilder merges and sorts remote, block, and tracker events', function (): void {
    if (!class_exists('App\\Databases\\TrackerEventTable')) {
        require app_class_file('App\\Databases\\TrackerEventTable');
    }

    $items = TrackerMaintenanceTimelineBuilder::build(
        [[
            'time' => '2026-05-14 09:00:00',
            'action' => 'update_theme',
            'message' => 'Theme updated',
            'status' => 'success',
        ]],
        [[
            'time' => '2026-05-15 10:00:00',
            'message' => '<strong>Block synced</strong>',
        ]],
        [[
            'channel' => 'tracker',
            'payload' => json_encode([
                'logs' => [[
                    'type' => 'client_request',
                    'request_id' => 'REQ-1',
                ]],
            ]),
            'event_type' => 'client_request',
            'status' => 'delivered',
            'created_at' => '2026-05-15 08:00:00',
            'delivered_at' => '2026-05-15 11:00:00',
        ]],
        10
    );

    assert_same('Yêu cầu hỗ trợ', $items[0]['title']);
    assert_same('Cập nhật block giao diện', $items[1]['title']);
    assert_same('Cập nhật theme từ xa', $items[2]['title']);
});
