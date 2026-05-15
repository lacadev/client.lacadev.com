<?php

declare(strict_types=1);

use App\Settings\MaintenanceModeManager;
use App\Settings\Tracker\MaintenanceSnapshot;

if (!function_exists('current_time')) {
    function current_time(string $type): string|int
    {
        return $type === 'mysql' ? '2026-05-15 10:00:00' : 1778814000;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['__lacadev_test_options'][$key] ?? $default;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://client.test' . $path;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show): string
    {
        return $show === 'version' ? '6.8.1' : 'Client Site';
    }
}

if (!function_exists('get_stylesheet')) {
    function get_stylesheet(): string
    {
        return 'lacadev-client';
    }
}

if (!function_exists('get_template')) {
    function get_template(): string
    {
        return 'lacadev-client';
    }
}

test('MaintenanceSnapshot captures stable core maintenance context', function (): void {
    $GLOBALS['__lacadev_test_options'] = [
        MaintenanceModeManager::OPT_ACTIVE => '1',
        'active_plugins' => ['seo/seo.php', 'forms/forms.php'],
    ];

    $snapshot = MaintenanceSnapshot::capture('update_core', '');

    assert_same('2026-05-15 10:00:00', $snapshot['time']);
    assert_same('https://client.test/', $snapshot['site_url']);
    assert_same('6.8.1', $snapshot['wp_version']);
    assert_same('lacadev-client', $snapshot['stylesheet']);
    assert_same('lacadev-client', $snapshot['template']);
    assert_true($snapshot['maintenance_active']);
    assert_same(2, $snapshot['active_plugins_count']);
    assert_same(['type' => 'core', 'version' => '6.8.1'], $snapshot['target']);
});
