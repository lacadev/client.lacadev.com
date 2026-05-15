<?php

declare(strict_types=1);

use App\Settings\Tracker\RemoteUpdateHistoryStore;

if (!function_exists('sanitize_key')) {
    function sanitize_key(mixed $value): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value): string
    {
        return strip_tags($value);
    }
}

test('RemoteUpdateHistoryStore appends normalized maintenance events', function (): void {
    $history = RemoteUpdateHistoryStore::append(
        [['time' => 'old']],
        'Update Theme!',
        '<b>my-theme</b>',
        'Success!',
        '<strong>Done</strong>',
        ['ok' => true],
        '2026-05-15 11:00:00'
    );

    assert_same('2026-05-15 11:00:00', $history[0]['time']);
    assert_same('updatetheme', $history[0]['action']);
    assert_same('my-theme', $history[0]['slug']);
    assert_same('success', $history[0]['status']);
    assert_same('Done', $history[0]['message']);
    assert_same(['ok' => true], $history[0]['meta']);
});
