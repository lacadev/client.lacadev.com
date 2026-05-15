<?php

declare(strict_types=1);

use App\Settings\Tracker\TrackerClientConfig;

if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['__lacadev_test_options'][$key] ?? $default;
    }
}

test('TrackerClientConfig reads endpoint and secret from option fallback', function (): void {
    $GLOBALS['__lacadev_test_options'] = [
        '_' . TrackerClientConfig::FIELD_ENDPOINT => ' https://lacadev.test/wp-json/laca/v1/tracker/log ',
        '_' . TrackerClientConfig::FIELD_SECRET => ' sk_test ',
    ];

    assert_same('https://lacadev.test/wp-json/laca/v1/tracker/log', TrackerClientConfig::endpoint());
    assert_same('sk_test', TrackerClientConfig::secretKey());
    assert_true(TrackerClientConfig::isConfigured());
});

test('TrackerClientConfig reports unconfigured when either value is missing', function (): void {
    $GLOBALS['__lacadev_test_options'] = [
        '_' . TrackerClientConfig::FIELD_ENDPOINT => 'https://lacadev.test/wp-json/laca/v1/tracker/log',
        '_' . TrackerClientConfig::FIELD_SECRET => '',
    ];

    assert_true(!TrackerClientConfig::isConfigured());
});
