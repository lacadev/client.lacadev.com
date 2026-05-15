<?php

declare(strict_types=1);

use App\Settings\Tracker\TrackerQueuePolicy;

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

test('TrackerQueuePolicy maps attempts to stable backoff windows', function (): void {
    assert_same(5, TrackerQueuePolicy::backoffMinutes(0));
    assert_same(5, TrackerQueuePolicy::backoffMinutes(1));
    assert_same(15, TrackerQueuePolicy::backoffMinutes(2));
    assert_same(60, TrackerQueuePolicy::backoffMinutes(3));
    assert_same(180, TrackerQueuePolicy::backoffMinutes(4));
});

test('TrackerQueuePolicy calculates next attempt timestamps through formatter callback', function (): void {
    $result = TrackerQueuePolicy::nextAttemptAt(
        2,
        1000,
        static fn(int $timestamp): string => 'ts-' . $timestamp
    );

    assert_same('ts-1900', $result);
});

test('TrackerQueuePolicy marks events permanent only at the max attempt threshold', function (): void {
    assert_true(!TrackerQueuePolicy::shouldFailPermanently(4, 5));
    assert_true(TrackerQueuePolicy::shouldFailPermanently(5, 5));
    assert_true(TrackerQueuePolicy::shouldFailPermanently(6, 5));
});
