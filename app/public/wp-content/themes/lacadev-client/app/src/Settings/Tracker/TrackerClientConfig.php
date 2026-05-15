<?php

namespace App\Settings\Tracker;

/**
 * Reads tracker connection settings from Carbon Fields with option fallback.
 */
final class TrackerClientConfig
{
    public const FIELD_ENDPOINT = 'laca_tracker_endpoint';
    public const FIELD_SECRET = 'laca_tracker_secret_key';

    public static function endpoint(): string
    {
        return self::read(self::FIELD_ENDPOINT);
    }

    public static function secretKey(): string
    {
        return self::read(self::FIELD_SECRET);
    }

    public static function isConfigured(): bool
    {
        return self::endpoint() !== '' && self::secretKey() !== '';
    }

    public static function read(string $field): string
    {
        if (function_exists('carbon_get_theme_option')) {
            return trim((string) (carbon_get_theme_option($field) ?: ''));
        }

        return trim((string) get_option('_' . $field, ''));
    }
}
