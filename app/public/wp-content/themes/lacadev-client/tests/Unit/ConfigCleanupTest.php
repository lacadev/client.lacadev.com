<?php

declare(strict_types=1);

test('WPEmerge config no longer references empty placeholder providers or routes', function (): void {
    $root = dirname(__DIR__, 2);
    $config = file_get_contents($root . '/app/config.php');

    assert_true(is_string($config) && $config !== '', 'Unable to read app/config.php');

    foreach ([
        'RouteConditionsServiceProvider',
        'ViewServiceProvider',
        'ModuleServiceProvider',
        "APP_APP_ROUTES_DIR . 'admin.php'",
        "APP_APP_ROUTES_DIR . 'ajax.php'",
    ] as $obsoleteReference) {
        assert_true(
            !str_contains($config, $obsoleteReference),
            "Obsolete config reference should stay removed: {$obsoleteReference}"
        );
    }
});

test('removed placeholder files stay removed', function (): void {
    $root = dirname(__DIR__, 2);

    foreach ([
        '/app/views.php',
        '/app/routes/admin.php',
        '/app/routes/ajax.php',
        '/app/src/Routing/RouteConditionsServiceProvider.php',
        '/app/src/View/ViewServiceProvider.php',
        '/app/src/Module/ModuleServiceProvider.php',
        '/app/src/Module/ModuleLoader.php',
        '/app/src/Module/ModuleInterface.php',
    ] as $removedFile) {
        assert_true(!is_file($root . $removedFile), "Removed placeholder file exists again: {$removedFile}");
    }
});
