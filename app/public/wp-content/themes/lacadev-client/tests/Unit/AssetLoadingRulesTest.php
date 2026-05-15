<?php

declare(strict_types=1);

use App\Assets\AssetLoadingRules;
use App\Contracts\AssetHandles;

test('AssetLoadingRules adds defer and async attributes only to configured script handles', function (): void {
    $tag = '<script src="https://example.test/theme.js"></script>';

    assert_same(
        '<script defer src="https://example.test/theme.js"></script>',
        AssetLoadingRules::scriptTag($tag, AssetHandles::THEME_JS)
    );

    assert_same(
        '<script async src="https://example.test/theme.js"></script>',
        AssetLoadingRules::scriptTag($tag, 'google-analytics')
    );

    assert_same($tag, AssetLoadingRules::scriptTag($tag, AssetHandles::VENDORS_JS));
});

test('AssetLoadingRules appends browser resource hints by relation type', function (): void {
    assert_same(
        ['existing', 'https://fonts.gstatic.com', 'https://ajax.googleapis.com'],
        AssetLoadingRules::resourceHints(['existing'], 'preconnect')
    );

    assert_same(
        ['//fonts.googleapis.com', '//cdnjs.cloudflare.com'],
        AssetLoadingRules::resourceHints([], 'dns-prefetch')
    );

    assert_same(
        ['https://example.test/blog/'],
        AssetLoadingRules::resourceHints([], 'prefetch', true, 'https://example.test/blog/')
    );

    assert_same([], AssetLoadingRules::resourceHints([], 'prefetch', false, 'https://example.test/blog/'));
});
