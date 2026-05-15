<?php

declare(strict_types=1);

use App\Assets\AssetPreloader;

if (!function_exists('esc_url')) {
    function esc_url(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

test('AssetPreloader builds preload tags for the bundled theme fonts', function (): void {
    $html = AssetPreloader::fontPreloadTags('https://example.test/dist/');

    assert_true(substr_count($html, 'rel="preload"') === 3);
    assert_true(str_contains($html, 'fonts/BeVietnamPro-Regular.bbe77399f9.ttf'));
    assert_true(str_contains($html, 'fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf'));
    assert_true(str_contains($html, 'fonts/Quicksand-Regular.61504eaec8.ttf'));
    assert_true(str_contains($html, 'crossorigin'));
});

test('AssetPreloader inlines critical CSS and preloads critical JS when present', function (): void {
    $existingFiles = [
        '/theme/dist/styles/critical.css' => 'body{background:#fff;}',
        '/theme/dist/critical.js' => 'console.log("critical");',
    ];

    $html = AssetPreloader::frontendPreloadHtml(
        '/theme/dist/',
        'https://example.test/dist/',
        static fn(string $path): bool => array_key_exists($path, $existingFiles),
        static fn(string $path): string => $existingFiles[$path] ?? ''
    );

    assert_true(str_contains($html, '<style id="critical-css">body{background:#fff;}</style>'));
    assert_true(str_contains($html, 'href="https://example.test/dist/critical.js" as="script"'));
    assert_true(!str_contains($html, 'styles/theme.css" as="style"'));
});

test('AssetPreloader preloads the main stylesheet when critical CSS is unavailable', function (): void {
    $html = AssetPreloader::frontendPreloadHtml(
        '/theme/dist/',
        'https://example.test/dist/',
        static fn(string $path): bool => false,
        static fn(string $path): string => ''
    );

    assert_true(str_contains($html, 'href="https://example.test/dist/styles/theme.css" as="style"'));
    assert_true(str_contains($html, 'fonts/BeVietnamPro-Regular.bbe77399f9.ttf'));
});
