<?php

declare(strict_types=1);

use App\Settings\Admin\AdminOptionHtml;

if (!function_exists('esc_html')) {
    function esc_html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

test('AdminOptionHtml renders block sync api and endpoint snippets safely', function (): void {
    $apiHtml = AdminOptionHtml::blockSyncApiKey('abc<unsafe>');
    $endpointHtml = AdminOptionHtml::blockSyncEndpoint('https://example.test/wp-json/lacadev/v1/sync-block');

    assert_true(str_contains($apiHtml, 'API Key'));
    assert_true(str_contains($apiHtml, 'abc&lt;unsafe&gt;'));
    assert_true(str_contains($endpointHtml, 'Endpoint URL'));
    assert_true(str_contains($endpointHtml, 'sync-block'));
});

test('AdminOptionHtml renders tracker status variants and notes', function (): void {
    assert_true(str_contains(AdminOptionHtml::trackerInfo(), 'LacaDev Tracker'));
    assert_true(str_contains(AdminOptionHtml::trackerStatus(true), 'Tracker đã được cấu hình'));
    assert_true(str_contains(AdminOptionHtml::trackerStatus(false), 'Chưa cấu hình'));
    assert_true(str_contains(AdminOptionHtml::trackerSaveNote(), 'wp-content/uploads'));
});
