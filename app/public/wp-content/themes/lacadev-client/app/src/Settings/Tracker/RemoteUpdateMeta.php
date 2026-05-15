<?php

namespace App\Settings\Tracker;

/**
 * Builds consistent maintenance-event metadata for remote updates.
 */
final class RemoteUpdateMeta
{
    public static function build(
        array $preflight,
        array $snapshotBefore,
        array $snapshotAfter,
        bool $temporaryMaintenanceEnabled,
        string $rollbackNote = ''
    ): array {
        $meta = [
            'preflight' => $preflight,
            'snapshot_before' => $snapshotBefore,
            'snapshot_after' => $snapshotAfter,
            'temporary_maintenance' => $temporaryMaintenanceEnabled,
        ];

        if ($rollbackNote !== '') {
            $meta['rollback_note'] = $rollbackNote;
        }

        return $meta;
    }
}
