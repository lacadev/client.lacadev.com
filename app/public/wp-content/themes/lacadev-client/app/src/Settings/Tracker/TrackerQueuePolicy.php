<?php

namespace App\Settings\Tracker;

/**
 * Retry/backoff rules for local tracker event queue delivery.
 */
final class TrackerQueuePolicy
{
    public static function nextAttemptAt(int $attempts, int $currentTimestamp, callable $dateFormatter): string
    {
        return $dateFormatter($currentTimestamp + self::backoffMinutes($attempts) * MINUTE_IN_SECONDS);
    }

    public static function shouldFailPermanently(int $attempts, int $maxAttempts): bool
    {
        return $attempts >= $maxAttempts;
    }

    public static function backoffMinutes(int $attempts): int
    {
        return match (true) {
            $attempts <= 1 => 5,
            $attempts === 2 => 15,
            $attempts === 3 => 60,
            default => 180,
        };
    }
}
