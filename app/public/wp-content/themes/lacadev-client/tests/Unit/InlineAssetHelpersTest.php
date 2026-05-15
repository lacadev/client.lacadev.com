<?php

declare(strict_types=1);

use App\Assets\AdminStyleOverrides;
use App\Assets\EditorStyleData;
use App\Assets\LoginInlineAssets;
use App\Assets\ReadingModeInlineAssets;

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_URL) ?: '';
    }
}

test('AdminStyleOverrides keeps important admin UI selectors and design tokens', function (): void {
    $css = AdminStyleOverrides::css();

    assert_true(str_contains($css, '--laca-admin-accent: #2563eb'));
    assert_true(str_contains($css, '#wpadminbar'));
    assert_true(str_contains($css, '.laca-cf-builder-shell'));
    assert_true(str_contains($css, '.swal2-popup'));
});

test('LoginInlineAssets keeps login placeholders and logo CSS configurable', function (): void {
    $script = LoginInlineAssets::placeholderScript();

    assert_true(str_contains($script, 'window.loginI18n'));
    assert_true(str_contains($script, "document.getElementById('user_login')"));
    assert_true(str_contains($script, "document.getElementById('user_pass')"));
    assert_true(str_contains($script, "setAttribute('placeholder'"));

    assert_same(
        "#login h1 a{background-image:url('https://example.test/logo.svg') !important;}",
        LoginInlineAssets::logoCss('https://example.test/logo.svg')
    );
});

test('EditorStyleData renders the editor CSS variables from theme options', function (): void {
    $css = EditorStyleData::cssVariables([
        'primary_color' => '#111111',
        'secondary_color' => '#222222',
        'bg_color' => '#f8f8f8',
        'primary_color_dark' => '#aaaaaa',
        'secondary_color_dark' => '#bbbbbb',
        'bg_color_dark' => '#000000',
    ]);

    assert_true(str_contains($css, '--primary-color: #111111;'));
    assert_true(str_contains($css, '--secondary-color-dark: #bbbbbb;'));
    assert_true(str_contains($css, "font-family: 'Quicksand', sans-serif !important;"));
});

test('ReadingModeInlineAssets clears disabled reading mode browser state', function (): void {
    $script = ReadingModeInlineAssets::disabledScript();

    assert_true(str_contains($script, "localStorage.removeItem('lacadev_reading_mode')"));
    assert_true(str_contains($script, "document.body.classList.remove('reading-mode')"));
    assert_true(str_contains($script, "document.getElementById('reading-mode-btn')"));
    assert_true(str_contains($script, 'new MutationObserver'));
});
