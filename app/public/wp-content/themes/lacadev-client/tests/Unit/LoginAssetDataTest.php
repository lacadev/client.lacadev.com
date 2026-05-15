<?php

declare(strict_types=1);

use App\Assets\LoginAssetData;

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

test('LoginAssetData resolves Carbon image values without enqueue side effects', function (): void {
    $resolver = static fn(int $id): string => "https://example.test/media/{$id}.png";

    assert_same('https://example.test/media/42.png', LoginAssetData::resolveLogoUrl(42, $resolver));
    assert_same('https://example.test/logo.svg', LoginAssetData::resolveLogoUrl(['url' => 'https://example.test/logo.svg'], $resolver));
    assert_same('https://example.test/media/7.png', LoginAssetData::resolveLogoUrl(['id' => '7'], $resolver));
    assert_same('', LoginAssetData::resolveLogoUrl(['unexpected' => 'value'], $resolver));
});

test('LoginAssetData builds locale labels with language keys and legacy fallback', function (): void {
    $options = [
        'login_user_label_vi' => 'Tên đăng nhập',
        'login_user_placeholder' => 'Legacy placeholder',
        'login_welcome_text_en' => "Hello\nStation",
    ];

    $locales = LoginAssetData::buildLocales(static fn(string $key) => $options[$key] ?? '');

    assert_same('Tên đăng nhập', $locales['vi']['userLabel']);
    assert_same('Legacy placeholder', $locales['vi']['userPlaceholder']);
    assert_true(str_contains($locales['en']['welcomeText'], '<br'));
    assert_same('The Key', $locales['en']['passLabel']);
});

test('LoginAssetData builds the localized payload used by login.js', function (): void {
    $locales = LoginAssetData::buildLocales(static fn(string $key) => '');
    $payload = LoginAssetData::buildPayload('https://example.test/logo.png', $locales, 'vi', 'https://example.test/');

    assert_same('https://example.test/logo.png', $payload['logoUrl']);
    assert_same($locales, $payload['locales']);
    assert_same($locales['vi']['userLabel'], $payload['userLabel']);
    assert_same('vi', $payload['language']);
    assert_same('https://example.test/', $payload['homeUrl']);
});
