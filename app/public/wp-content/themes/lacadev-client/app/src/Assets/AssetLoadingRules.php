<?php

namespace App\Assets;

use App\Contracts\AssetHandles;

/**
 * Centralized rules for script loading attributes and browser resource hints.
 */
final class AssetLoadingRules
{
    private const DEFER_SCRIPTS = [
        AssetHandles::THEME_JS,
        AssetHandles::ADMIN_JS,
        AssetHandles::LOGIN_JS,
        AssetHandles::EDITOR_JS,
        AssetHandles::ARCHIVE_JS,
        AssetHandles::COMMENTS_JS,
    ];

    private const ASYNC_SCRIPTS = [
        'google-analytics',
        'facebook-pixel',
        'hotjar',
    ];

    public static function scriptTag(string $tag, string $handle): string
    {
        if (in_array($handle, self::DEFER_SCRIPTS, true)) {
            return str_replace('<script ', '<script defer ', $tag);
        }

        if (in_array($handle, self::ASYNC_SCRIPTS, true)) {
            return str_replace('<script ', '<script async ', $tag);
        }

        return $tag;
    }

    public static function resourceHints(
        array $hints,
        string $relationType,
        bool $shouldPrefetchPostsPage = false,
        string $postsPagePermalink = ''
    ): array {
        if ($relationType === 'preconnect') {
            $hints[] = 'https://fonts.gstatic.com';
            $hints[] = 'https://ajax.googleapis.com';
        }

        if ($relationType === 'dns-prefetch') {
            $hints[] = '//fonts.googleapis.com';
            $hints[] = '//cdnjs.cloudflare.com';
        }

        if ($relationType === 'prefetch' && $shouldPrefetchPostsPage && $postsPagePermalink !== '') {
            $hints[] = $postsPagePermalink;
        }

        return $hints;
    }
}
