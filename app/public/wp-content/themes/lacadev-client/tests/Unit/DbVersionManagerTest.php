<?php

declare(strict_types=1);

use App\Databases\DbVersionManager;

if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['__lacadev_test_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, mixed $value, mixed $autoload = null): bool
    {
        $GLOBALS['__lacadev_test_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        unset($GLOBALS['__lacadev_test_options'][$key]);
        return true;
    }
}

function reset_lacadev_test_options(): void
{
    $GLOBALS['__lacadev_test_options'] = [];
}

test('DbVersionManager runs installers only when schema version increases', function (): void {
    reset_lacadev_test_options();
    $runs = 0;

    DbVersionManager::maybeInstall('1.1.0', 'schema_version', [
        static function () use (&$runs): void {
            $runs++;
        },
    ]);

    assert_same(1, $runs);
    assert_same('1.1.0', get_option('schema_version'));

    DbVersionManager::maybeInstall('1.1.0', 'schema_version', [
        static function () use (&$runs): void {
            $runs++;
        },
    ]);

    assert_same(1, $runs, 'Installer should not run again for an already installed version.');
});

test('DbVersionManager force install always runs installers and reset deletes version', function (): void {
    reset_lacadev_test_options();
    update_option('schema_version', '2.0.0', false);
    $runs = 0;

    DbVersionManager::forceInstall('2.0.1', 'schema_version', [
        static function () use (&$runs): void {
            $runs++;
        },
    ]);

    assert_same(1, $runs);
    assert_same('2.0.1', DbVersionManager::installedVersion('schema_version'));

    DbVersionManager::reset('schema_version');
    assert_same(null, DbVersionManager::installedVersion('schema_version'));
});
