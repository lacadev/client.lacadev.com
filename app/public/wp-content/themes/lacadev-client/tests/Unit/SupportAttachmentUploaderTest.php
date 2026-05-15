<?php

declare(strict_types=1);

use App\Settings\Tracker\SupportAttachmentUploader;

if (!defined('MB_IN_BYTES')) {
    define('MB_IN_BYTES', 1048576);
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = ''
        ) {
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $value): bool
    {
        return $value instanceof WP_Error;
    }
}

test('SupportAttachmentUploader rejects requests with too many files', function (): void {
    $result = SupportAttachmentUploader::uploadNormalized(
        array_fill(0, 6, [
            'name' => 'screen.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]),
        'REQ1234567',
        static fn(array $file, array $args): array => [],
        static fn(array $args, string $file): int => 1,
        static fn(int $attachmentId, string $file): array => [],
        static fn(int $attachmentId, array $metadata): bool => true,
        static fn(string $value): string => $value,
        static fn(string $value): string => $value,
        static fn(string $value): string => $value,
        static fn(int $length): string => 'abcdef',
        static fn(): int => 1700000000
    );

    assert_true(is_wp_error($result));
    assert_same('too_many_files', $result->get_error_code());
});

test('SupportAttachmentUploader rejects oversize files before upload', function (): void {
    $result = SupportAttachmentUploader::uploadNormalized(
        [[
            'name' => 'screen.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK,
            'size' => 6 * MB_IN_BYTES,
        ]],
        'REQ1234567',
        static fn(array $file, array $args): array => [],
        static fn(array $args, string $file): int => 1,
        static fn(int $attachmentId, string $file): array => [],
        static fn(int $attachmentId, array $metadata): bool => true,
        static fn(string $value): string => $value,
        static fn(string $value): string => $value,
        static fn(string $value): string => $value,
        static fn(int $length): string => 'abcdef',
        static fn(): int => 1700000000
    );

    assert_true(is_wp_error($result));
    assert_same('file_too_large', $result->get_error_code());
});

test('SupportAttachmentUploader uploads files and normalizes attachment payloads', function (): void {
    $capturedArgs = [];
    $updatedMetadata = [];

    $result = SupportAttachmentUploader::uploadNormalized(
        [[
            'name' => 'screen one.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
        ]],
        'REQ1234567',
        static function (array $file, array $args) use (&$capturedArgs): array {
            $capturedArgs = $args;

            return [
                'type' => 'image/png',
                'file' => '/uploads/support-req1234567.png',
                'url' => ' https://client.test/uploads/support-req1234567.png ',
            ];
        },
        static fn(array $args, string $file): int => 99,
        static fn(int $attachmentId, string $file): array => ['sizes' => ['thumbnail' => true]],
        static function (int $attachmentId, array $metadata) use (&$updatedMetadata): bool {
            $updatedMetadata = [$attachmentId, $metadata];

            return true;
        },
        static fn(string $value): string => preg_replace('/[^a-zA-Z0-9.\-_]/', '', $value) ?? '',
        static fn(string $value): string => trim(strip_tags($value)),
        static fn(string $value): string => trim($value),
        static fn(int $length): string => 'abc123',
        static fn(): int => 1700000000
    );

    assert_same([
        [
            'id' => 99,
            'url' => 'https://client.test/uploads/support-req1234567.png',
            'name' => 'screenone.png',
            'type' => 'image/png',
        ],
    ], $result);
    assert_same([99, ['sizes' => ['thumbnail' => true]]], $updatedMetadata);
    assert_same(false, $capturedArgs['test_form']);
    assert_same('image/png', $capturedArgs['mimes']['png']);
    assert_same(
        'support-req1234567-1700000000-abc123.png',
        $capturedArgs['unique_filename_callback']('/tmp', 'screen', '.png')
    );
});
