<?php

namespace App\Settings\Tracker;

/**
 * Stores remote-maintenance history entries in a normalized format.
 */
final class RemoteUpdateHistoryStore
{
    public static function append(array $history, string $action, string $slug, string $status, string $message, array $meta, string $time): array
    {
        return RemoteUpdateHistory::prepend(RemoteUpdateHistory::normalize($history), [
            'time' => $time,
            'action' => $action,
            'slug' => $slug,
            'status' => $status,
            'message' => $message,
            'meta' => $meta,
        ]);
    }
}
