<?php

declare(strict_types=1);

use App\Features\ContactForm\ContactFormDefaults;

if (!function_exists('esc_html')) {
    function esc_html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

test('ContactFormDefaults provides the default form rows', function (): void {
    $rows = ContactFormDefaults::rows();

    assert_same(3, count($rows));
    assert_same('name', $rows[0]['cols'][0]['fields'][0]['name']);
    assert_same('phone_number', $rows[0]['cols'][1]['fields'][0]['name']);
    assert_same('message', $rows[2]['cols'][0]['fields'][0]['name']);
});

test('ContactFormDefaults provides admin and customer email templates', function (): void {
    $admin = ContactFormDefaults::adminEmailBody();
    $customer = ContactFormDefaults::customerEmailBody('Laca <Dev>');

    assert_true(str_contains($admin, 'Thông báo liên hệ mới'));
    assert_true(str_contains($admin, '$message'));
    assert_true(str_contains($customer, 'Đã nhận lời nhắn'));
    assert_true(str_contains($customer, 'Laca &lt;Dev&gt;'));
});
