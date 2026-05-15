<?php

declare(strict_types=1);

use App\Bootstrap\FeatureRegistry;
use App\Settings\RequirePlugins;

function feature_registry_classes(array $manifest): array
{
    $classes = [];

    foreach ($manifest as $group) {
        foreach ($group as $key => $value) {
            $classes[] = is_string($key) && str_contains($key, '\\') ? $key : $value;
        }
    }

    return array_values(array_unique(array_filter($classes, static fn($class) => is_string($class) && str_contains($class, '\\'))));
}

test('feature registry exposes the active bootstrap groups', function (): void {
    $manifest = FeatureRegistry::manifest();

    foreach ([
        'immediate_constructors',
        'immediate_late_constructors',
        'immediate_initializers',
        'dynamic_post_type_constructors',
        'admin_init_constructors',
        'admin_init_registrars',
        'init_priority_five_static_registrars',
        'init_priority_five_constructors',
        'init_priority_one_initializers',
        'init_priority_one_constructors',
        'init_default_initializers',
        'init_default_constructors',
        'init_default_late_initializers',
    ] as $group) {
        assert_true(array_key_exists($group, $manifest), "Missing manifest group: {$group}");
    }

    assert_true(in_array(RequirePlugins::class, $manifest['immediate_constructors'], true));
});

test('feature registry classes resolve to existing PSR-4 files', function (): void {
    foreach (feature_registry_classes(FeatureRegistry::manifest()) as $class) {
        $file = app_class_file($class);

        assert_true($file !== false, "Missing App PSR-4 mapping for {$class}");
        assert_true(is_file($file), "Autoload target does not exist for {$class}: {$file}");
    }
});

test('feature registry preserves expected init hook priorities', function (): void {
    $hooks = FeatureRegistry::hookManifest();
    $byCallback = [];

    foreach ($hooks as $hook) {
        $byCallback[$hook['callback']] = $hook;
    }

    assert_same(5, $byCallback['bootPriorityFiveInitFeatures']['priority']);
    assert_same(1, $byCallback['bootPriorityOneInitFeatures']['priority']);
    assert_same(10, $byCallback['bootDefaultInitFeatures']['priority']);
    assert_same('is_admin', $byCallback['bootAdminFeatures']['condition']);
});

test('RequirePlugins no longer bootstraps itself as an autoload side effect', function (): void {
    $file = dirname(__DIR__, 2) . '/app/src/Settings/RequirePlugins.php';
    $source = file_get_contents($file);

    assert_true(is_string($source) && $source !== '', 'Unable to read RequirePlugins.php');
    assert_true(
        preg_match('/^\s*new\s+RequirePlugins\s*\(/m', $source) !== 1,
        'RequirePlugins.php should not instantiate itself at file scope.'
    );
});
