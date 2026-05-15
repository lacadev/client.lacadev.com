<?php

namespace App\Settings\Tracker;

/**
 * Handles support-request image uploads and attachment normalization.
 */
final class SupportAttachmentUploader
{
    private const MAX_FILES = 5;
    private const MAX_FILE_BYTES = 5 * MB_IN_BYTES;
    private const ALLOWED_MIMES = [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    public static function upload(array $fileParams, string $requestId): array|\WP_Error
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        return self::uploadNormalized(
            SupportAttachmentFiles::normalize($fileParams),
            $requestId,
            static fn(array $file, array $args): array => wp_handle_upload($file, $args),
            static fn(array $args, string $file): int|\WP_Error => wp_insert_attachment($args, $file),
            static fn(int $attachmentId, string $file): array => wp_generate_attachment_metadata($attachmentId, $file),
            static fn(int $attachmentId, array $metadata): bool => (bool) wp_update_attachment_metadata($attachmentId, $metadata),
            static fn(string $value): string => sanitize_file_name($value),
            static fn(string $value): string => sanitize_text_field($value),
            static fn(string $value): string => esc_url_raw($value),
            static fn(int $length): string => wp_generate_password($length, false),
            static fn(): int => time()
        );
    }

    public static function uploadNormalized(
        array $files,
        string $requestId,
        callable $handleUpload,
        callable $insertAttachment,
        callable $generateAttachmentMetadata,
        callable $updateAttachmentMetadata,
        callable $sanitizeFileName,
        callable $sanitizeTextField,
        callable $sanitizeUrl,
        callable $generatePassword,
        callable $timeProvider
    ): array|\WP_Error {
        if (empty($files)) {
            return [];
        }

        if (count($files) > self::MAX_FILES) {
            return new \WP_Error('too_many_files', 'Chỉ được đính kèm tối đa 5 hình ảnh.');
        }

        $attachments = [];
        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return new \WP_Error('upload_error', 'Không tải được một hình ảnh đính kèm.');
            }

            if ((int) ($file['size'] ?? 0) > self::MAX_FILE_BYTES) {
                return new \WP_Error('file_too_large', 'Mỗi hình ảnh đính kèm tối đa 5MB.');
            }

            $handled = $handleUpload($file, [
                'test_form' => false,
                'mimes' => self::ALLOWED_MIMES,
                'unique_filename_callback' => static function ($dir, $name, $ext) use ($requestId, $sanitizeFileName, $generatePassword, $timeProvider) {
                    return $sanitizeFileName(
                        'support-' . strtolower($requestId) . '-' . $timeProvider() . '-' . $generatePassword(6) . $ext
                    );
                },
            ]);

            if (!empty($handled['error'])) {
                return new \WP_Error('upload_error', $sanitizeTextField((string) $handled['error']));
            }

            $attachmentId = $insertAttachment([
                'post_mime_type' => $handled['type'] ?? '',
                'post_title' => $sanitizeFileName(pathinfo((string) ($handled['file'] ?? ''), PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            ], (string) ($handled['file'] ?? ''));

            if (!is_wp_error($attachmentId)) {
                $metadata = $generateAttachmentMetadata((int) $attachmentId, (string) ($handled['file'] ?? ''));
                $updateAttachmentMetadata((int) $attachmentId, $metadata);
            }

            $attachments[] = [
                'id' => is_wp_error($attachmentId) ? 0 : (int) $attachmentId,
                'url' => $sanitizeUrl((string) ($handled['url'] ?? '')),
                'name' => $sanitizeFileName((string) ($file['name'] ?? '')),
                'type' => $sanitizeTextField((string) ($handled['type'] ?? '')),
            ];
        }

        return $attachments;
    }
}
