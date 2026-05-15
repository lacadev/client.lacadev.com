<?php

namespace App\Settings\Tracker;

/**
 * Formats tracker health and summary counters for dashboard/reporting UIs.
 */
final class TrackerHealthSummary
{
    public static function build(array $health, bool $configured, array $queueCounts): array
    {
        return [
            'configured' => $configured,
            'last_success_at' => (string) ($health['last_success_at'] ?? ''),
            'last_failure_at' => (string) ($health['last_failure_at'] ?? ''),
            'last_attempt_at' => (string) ($health['last_attempt_at'] ?? ''),
            'last_error' => (string) ($health['last_error'] ?? ''),
            'last_http_code' => $health['last_http_code'] ?? null,
            'queued' => (int) ($queueCounts['queued'] ?? 0),
            'retry' => (int) ($queueCounts['retry'] ?? 0),
            'failed' => (int) ($queueCounts['failed'] ?? 0),
            'delivered' => (int) ($queueCounts['delivered'] ?? 0),
        ];
    }

    public static function countRemoteHistoryStatuses(array $history): array
    {
        $counts = [];

        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }

            $status = self::sanitizeStatus((string) ($item['status'] ?? 'unknown'));
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    public static function countBlockDiagnostics(array $diagnostics): array
    {
        $counts = [
            'blocks' => 0,
            'warnings' => 0,
            'errors' => 0,
        ];

        foreach ($diagnostics as $item) {
            if (!is_array($item)) {
                continue;
            }

            $counts['blocks']++;
            $counts['warnings'] += count((array) ($item['warnings'] ?? []));
            $counts['errors'] += count((array) ($item['errors'] ?? []));
        }

        return $counts;
    }

    private static function sanitizeStatus(string $status): string
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($status);
        }

        $status = strtolower($status);
        return preg_replace('/[^a-z0-9_\-]/', '', $status) ?: 'unknown';
    }
}
