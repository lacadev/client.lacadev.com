<?php

declare(strict_types=1);

test('parent theme owns shared template parts used across parent and child templates', function (): void {
    $parentRoot = dirname(__DIR__, 2);

    foreach ([
        '/theme/template-parts/breadcrumb.php',
        '/theme/template-parts/page-hero.php',
        '/theme/template-parts/share_box.php',
        '/theme/template-parts/post-hero.php',
        '/theme/template-parts/rating-box.php',
        '/theme/template-parts/loop-post.php',
        '/theme/template-parts/loop-service.php',
        '/theme/template-parts/loop-product.php',
    ] as $requiredPartial) {
        assert_true(
            is_file($parentRoot . $requiredPartial),
            "Parent fallback partial is missing: {$requiredPartial}"
        );
    }
});

test('child theme stays lean and does not reintroduce placeholder routes or duplicate shared partials', function (): void {
    $parentRoot = dirname(__DIR__, 2);
    $childRoot = dirname($parentRoot) . '/lacadev-client-child';

    foreach ([
        '/app/routes/admin.php',
        '/app/routes/ajax.php',
        '/app/routes/web.php',
        '/theme/template-parts/breadcrumb.php',
        '/theme/template-parts/comment-single.php',
        '/theme/template-parts/loop-post.php',
        '/theme/template-parts/loop-service.php',
        '/theme/template-parts/page-hero.php',
        '/theme/template-parts/post-hero.php',
        '/theme/template-parts/rating-box.php',
        '/theme/template-parts/share_box.php',
    ] as $removedFile) {
        assert_true(
            !is_file($childRoot . $removedFile),
            "Child theme should stay free of duplicate/placeholder file: {$removedFile}"
        );
    }
});

test('child theme options stay dormant until project-specific fields are defined', function (): void {
    $parentRoot = dirname(__DIR__, 2);
    $file = dirname($parentRoot) . '/lacadev-client-child/theme/setup/theme-options.php';
    $contents = file_get_contents($file);

    assert_true(is_string($contents) && $contents !== '', 'Unable to read child theme-options.php');
    assert_true(
        str_contains($contents, "apply_filters('lacadev_child_theme_options_fields', [])"),
        'Child theme options should wait for explicit field definitions.'
    );
    assert_true(
        !str_contains($contents, 'Field::make('),
        'Empty Carbon Fields scaffolding should not stay in child theme options.'
    );
});
