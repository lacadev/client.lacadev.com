<?php

namespace App\Settings\Tracker;

/**
 * Normalizes support-center upload inputs from single/multiple file fields.
 */
final class SupportAttachmentFiles
{
    public static function normalize(array $fileParams): array
    {
        $raw = $fileParams['attachments'] ?? ($fileParams['attachment'] ?? null);
        if (empty($raw)) {
            return [];
        }

        if (!is_array($raw['name'] ?? null)) {
            return [$raw];
        }

        $files = [];
        foreach ($raw['name'] as $index => $name) {
            if ($name === '') {
                continue;
            }

            $files[] = [
                'name' => $name,
                'type' => $raw['type'][$index] ?? '',
                'tmp_name' => $raw['tmp_name'][$index] ?? '',
                'error' => $raw['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $raw['size'][$index] ?? 0,
            ];
        }

        return $files;
    }
}
