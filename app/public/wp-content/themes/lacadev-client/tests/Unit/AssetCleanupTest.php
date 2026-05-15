<?php

declare(strict_types=1);

test('favicon output has a non-empty svg fallback when ico is empty', function (): void {
    $root = dirname(__DIR__, 2);
    $assets = file_get_contents($root . '/theme/setup/assets.php');
    $favicon = $root . '/resources/images/favicon.ico';
    $svg = $root . '/resources/images/dev/icon.svg';

    assert_true(is_string($assets) && $assets !== '', 'Unable to read assets.php');
    assert_true(is_file($svg) && filesize($svg) > 0, 'SVG favicon fallback is missing.');
    assert_true(
        !is_file($favicon) || filesize($favicon) > 0,
        'Do not keep an empty favicon.ico file in resources.'
    );

    assert_true(
        str_contains($assets, "filesize(\$favicon_path) > 0"),
        'favicon.ico should be guarded before output.'
    );
    assert_true(
        str_contains($assets, 'images/dev/icon.svg'),
        'SVG favicon fallback should be used when favicon.ico is missing or empty.'
    );
});

test('theme entrypoint does not import the empty favicon asset', function (): void {
    $root = dirname(__DIR__, 2);
    $entrypoint = file_get_contents($root . '/resources/scripts/theme/index.js');

    assert_true(is_string($entrypoint) && $entrypoint !== '', 'Unable to read theme JS entrypoint.');
    assert_true(
        !str_contains($entrypoint, "@images/favicon.ico"),
        'Do not import the empty favicon.ico in the JS entrypoint.'
    );
});
