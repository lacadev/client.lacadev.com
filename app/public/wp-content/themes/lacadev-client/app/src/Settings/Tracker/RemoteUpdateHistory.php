<?php

namespace App\Settings\Tracker;

/**
 * Normalizes remote update history records before option persistence.
 */
final class RemoteUpdateHistory
{
    public static function prepend(array $history, array $event, int $limit = 50): array
    {
        array_unshift($history, [
            'time' => (string) ($event['time'] ?? ''),
            'action' => sanitize_key($event['action'] ?? ''),
            'slug' => sanitize_text_field($event['slug'] ?? ''),
            'status' => sanitize_key($event['status'] ?? ''),
            'message' => wp_strip_all_tags((string) ($event['message'] ?? '')),
            'meta' => is_array($event['meta'] ?? null) ? $event['meta'] : [],
        ]);

        return array_slice($history, 0, $limit);
    }

    public static function normalize(mixed $history): array
    {
        return is_array($history) ? $history : [];
    }
}
