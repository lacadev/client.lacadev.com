<?php

namespace App\Support;

/**
 * Resolves the best client IP from common proxy/server headers.
 */
final class ClientIpResolver
{
    private const SERVER_KEYS = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];

    public static function fromGlobals(string $fallback = ''): string
    {
        return self::fromServer($_SERVER, $fallback);
    }

    public static function fromServer(array $server, string $fallback = ''): string
    {
        foreach (self::SERVER_KEYS as $key) {
            $ip = self::firstValidIp((string) ($server[$key] ?? ''));
            if ($ip !== '') {
                return $ip;
            }
        }

        return $fallback;
    }

    private static function firstValidIp(string $value): string
    {
        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return '';
    }
}
