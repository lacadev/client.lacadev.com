<?php

declare(strict_types=1);

use App\Settings\Tracker\ClientSupportRequestBuilder;

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

test('ClientSupportRequestBuilder normalizes unsupported types to request', function (): void {
    assert_same('request', ClientSupportRequestBuilder::normalizeType('Something Else!'));
    assert_same('bug', ClientSupportRequestBuilder::normalizeType('Bug'));
});

test('ClientSupportRequestBuilder builds support log content and context', function (): void {
    $payload = ClientSupportRequestBuilder::build([
        'request_id' => 'REQ1234567',
        'request_type' => 'bug',
        'message' => 'Trang checkout loi',
        'site_url' => 'https://client.test/',
        'contact_name' => 'Anh',
        'contact_email' => 'anh@example.com',
        'page_url' => 'https://client.test/checkout',
        'ip' => '127.0.0.1',
        'user_agent' => '<b>Mozilla</b>',
        'attachments' => [
            ['url' => 'https://client.test/upload/a.png'],
            ['url' => 'https://client.test/upload/b.png'],
        ],
    ]);

    assert_same('bug', $payload['request_type']);
    assert_same('warning', $payload['log']['level']);
    assert_same('REQ1234567', $payload['context']['request_id']);
    assert_same('Mozilla', $payload['context']['user_agent']);
    assert_true(str_contains($payload['content'], 'Mã yêu cầu: REQ1234567'));
    assert_true(str_contains($payload['content'], '[Báo lỗi]'));
    assert_true(str_contains($payload['content'], 'Website: https://client.test/'));
    assert_true(str_contains($payload['content'], 'Trang gửi: https://client.test/checkout'));
    assert_true(str_contains($payload['content'], '- https://client.test/upload/a.png'));
    assert_same($payload['content'], $payload['log']['content']);
    assert_same($payload['context'], $payload['log']['meta']);
});
