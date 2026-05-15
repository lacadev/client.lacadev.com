<?php

namespace App\Settings\Tracker;

/**
 * Compares tracked file mtimes against a persisted baseline.
 */
final class TrackerFileIntegrity
{
    public static function compare(
        array $baseline,
        array $watchPaths,
        callable $fileExists,
        callable $fileMtime,
        callable $dateFormatter
    ): array {
        $current = [];
        $changed = [];

        foreach ($watchPaths as $absPath => $relLabel) {
            if (!$fileExists($absPath)) {
                continue;
            }

            $mtime = $fileMtime($absPath);
            $current[$relLabel] = $mtime;

            if (!empty($baseline[$relLabel]) && $baseline[$relLabel] !== $mtime) {
                $changed[] = $relLabel . ' (sửa lúc ' . $dateFormatter($mtime) . ')';
            }
        }

        foreach ($current as $label => $mtime) {
            if (!isset($baseline[$label])) {
                $changed[] = $label . ' [mới] (tạo lúc ' . $dateFormatter($mtime) . ')';
            }
        }

        return [
            'current' => $current,
            'changed' => empty($baseline) ? [] : $changed,
        ];
    }
}
