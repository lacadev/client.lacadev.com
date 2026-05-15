<?php

declare(strict_types=1);

use App\Settings\Tracker\RemoteUpdateRequestValidator;

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

test('RemoteUpdateRequestValidator rejects unauthorized and invalid requests', function (): void {
    $unauthorized = RemoteUpdateRequestValidator::validate(['secret_key' => 'bad'], 'good');
    assert_true(!$unauthorized['ok']);
    assert_same(401, $unauthorized['status']);

    $invalidAction = RemoteUpdateRequestValidator::validate([
        'secret_key' => 'good',
        'action' => 'something_else',
    ], 'good');
    assert_true(!$invalidAction['ok']);
    assert_same('Action không hợp lệ.', $invalidAction['message']);

    $missingSlug = RemoteUpdateRequestValidator::validate([
        'secret_key' => 'good',
        'action' => 'update_plugin',
    ], 'good');
    assert_true(!$missingSlug['ok']);
    assert_same('Thiếu slug plugin.', $missingSlug['message']);
});

test('RemoteUpdateRequestValidator normalizes valid requests', function (): void {
    $result = RemoteUpdateRequestValidator::validate([
        'secret_key' => 'good',
        'action' => 'UPDATE_THEME',
        'slug' => ' my-theme ',
        'dry_run' => '1',
    ], 'good');

    assert_true($result['ok']);
    assert_same('update_theme', $result['action']);
    assert_same('my-theme', $result['slug']);
    assert_true($result['dry_run']);
});
