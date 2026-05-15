<?php

declare(strict_types=1);

use App\Settings\Tracker\RemoteUpdateHistory;

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

test('RemoteUpdateHistory prepends normalized events and trims history', function (): void {
    $history = RemoteUpdateHistory::prepend([
        ['time' => 'old-1'],
        ['time' => 'old-2'],
    ], [
        'time' => '2026-05-15 10:00:00',
        'action' => 'Update Plugin!',
        'slug' => '<b>akismet/akismet.php</b>',
        'status' => 'Needs Review!',
        'message' => '<strong>Updated</strong>',
        'meta' => ['ok' => true],
    ], 2);

    assert_same(2, count($history));
    assert_same('2026-05-15 10:00:00', $history[0]['time']);
    assert_same('updateplugin', $history[0]['action']);
    assert_same('akismet/akismet.php', $history[0]['slug']);
    assert_same('needsreview', $history[0]['status']);
    assert_same('Updated', $history[0]['message']);
    assert_same(['ok' => true], $history[0]['meta']);
});

test('RemoteUpdateHistory normalizes invalid option values to an empty history', function (): void {
    assert_same([], RemoteUpdateHistory::normalize('bad'));
    assert_same([['time' => 'ok']], RemoteUpdateHistory::normalize([['time' => 'ok']]));
});
