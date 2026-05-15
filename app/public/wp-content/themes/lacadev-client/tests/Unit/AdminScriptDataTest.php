<?php

declare(strict_types=1);

use App\Assets\AdminScriptData;

test('AdminScriptData builds ajax params without touching WordPress globals', function (): void {
    assert_same([
        'ajaxurl' => 'https://example.test/wp-admin/admin-ajax.php',
        'nonce' => 'nonce-value',
    ], AdminScriptData::ajaxParams('https://example.test/wp-admin/admin-ajax.php', 'nonce-value'));
});

test('AdminScriptData keeps required admin i18n keys', function (): void {
    $data = AdminScriptData::i18n(static fn(string $text): string => 't:' . $text);

    assert_same('t:Remove Thumbnail?', $data['removeThumbnailTitle']);
    assert_same('t:Failed to remove thumbnail.', $data['failedRemove']);
    assert_same('t:Choose image', $data['chooseImage']);
    assert_same('t:Set featured image', $data['setFeaturedImage']);
});
