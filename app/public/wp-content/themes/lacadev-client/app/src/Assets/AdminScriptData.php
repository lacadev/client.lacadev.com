<?php

namespace App\Assets;

/**
 * Data payloads consumed by admin.js.
 */
final class AdminScriptData
{
    public static function ajaxParams(string $ajaxUrl, string $nonce): array
    {
        return [
            'ajaxurl' => $ajaxUrl,
            'nonce' => $nonce,
        ];
    }

    public static function i18n(callable $translate): array
    {
        return [
            'removeThumbnailTitle' => $translate('Remove Thumbnail?'),
            'removeThumbnailText' => $translate('Are you sure you want to remove this featured image?'),
            'removeThumbnailConfirm' => $translate('Yes, remove it'),
            'removeThumbnailCancel' => $translate('Cancel'),
            'removedTitle' => $translate('Removed!'),
            'removedText' => $translate('Featured image has been removed.'),
            'errorTitle' => $translate('Error!'),
            'failedRemove' => $translate('Failed to remove thumbnail.'),
            'chooseImage' => $translate('Choose image'),
            'setFeaturedImage' => $translate('Set featured image'),
        ];
    }
}
