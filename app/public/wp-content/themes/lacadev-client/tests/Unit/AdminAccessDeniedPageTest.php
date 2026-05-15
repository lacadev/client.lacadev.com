<?php

declare(strict_types=1);

use App\Settings\Admin\AdminAccessDeniedPage;

if (!function_exists('esc_url')) {
    function esc_url(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

test('AdminAccessDeniedPage renders the branded denial screen', function (): void {
    $html = AdminAccessDeniedPage::render(
        'https://example.test/logo.png',
        'https://example.test/wp-admin/',
        'https://lacadev.test/',
        'Laca Dev'
    );

    assert_true(str_contains($html, 'denied-card'));
    assert_true(str_contains($html, 'Peaceful Night'));
    assert_true(str_contains($html, 'Quay về Dashboard'));
    assert_same(100, substr_count($html, 'class="alp-star"'));
    assert_same(8, substr_count($html, 'class="alp-ember"'));
});
