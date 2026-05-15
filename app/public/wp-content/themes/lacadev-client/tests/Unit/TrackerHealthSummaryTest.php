<?php

declare(strict_types=1);

use App\Settings\Tracker\TrackerHealthSummary;

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?: '';
    }
}

test('TrackerHealthSummary builds stable health data with queue counts', function (): void {
    $summary = TrackerHealthSummary::build([
        'last_success_at' => '2026-05-15 10:00:00',
        'last_http_code' => 200,
    ], true, [
        'queued' => '3',
        'retry' => 1,
        'failed' => 2,
        'delivered' => 9,
    ]);

    assert_true($summary['configured']);
    assert_same('2026-05-15 10:00:00', $summary['last_success_at']);
    assert_same('', $summary['last_error']);
    assert_same(200, $summary['last_http_code']);
    assert_same(3, $summary['queued']);
    assert_same(9, $summary['delivered']);
});

test('TrackerHealthSummary counts remote statuses and block diagnostics', function (): void {
    $remoteCounts = TrackerHealthSummary::countRemoteHistoryStatuses([
        ['status' => 'success'],
        ['status' => 'failed'],
        ['status' => 'failed'],
        ['status' => 'Needs Review!'],
        'bad-item',
    ]);

    assert_same(1, $remoteCounts['success']);
    assert_same(2, $remoteCounts['failed']);
    assert_same(1, $remoteCounts['needsreview']);

    $diagnosticCounts = TrackerHealthSummary::countBlockDiagnostics([
        ['warnings' => ['missing-title'], 'errors' => ['bad-json', 'bad-render']],
        ['warnings' => ['missing-icon'], 'errors' => []],
        'bad-item',
    ]);

    assert_same(2, $diagnosticCounts['blocks']);
    assert_same(2, $diagnosticCounts['warnings']);
    assert_same(2, $diagnosticCounts['errors']);
});
