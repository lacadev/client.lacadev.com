<?php

declare(strict_types=1);

use App\Settings\Tracker\SupportAttachmentFiles;

test('SupportAttachmentFiles normalizes single upload fields', function (): void {
    $file = [
        'name' => 'screen.png',
        'type' => 'image/png',
        'tmp_name' => '/tmp/php123',
        'error' => UPLOAD_ERR_OK,
        'size' => 123,
    ];

    assert_same([$file], SupportAttachmentFiles::normalize(['attachment' => $file]));
});

test('SupportAttachmentFiles normalizes multiple upload fields and skips blanks', function (): void {
    $files = SupportAttachmentFiles::normalize([
        'attachments' => [
            'name' => ['a.png', '', 'b.jpg'],
            'type' => ['image/png', '', 'image/jpeg'],
            'tmp_name' => ['/tmp/a', '', '/tmp/b'],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            'size' => [10, 0, 20],
        ],
    ]);

    assert_same([
        [
            'name' => 'a.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/a',
            'error' => UPLOAD_ERR_OK,
            'size' => 10,
        ],
        [
            'name' => 'b.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/b',
            'error' => UPLOAD_ERR_OK,
            'size' => 20,
        ],
    ], $files);
});

test('SupportAttachmentFiles returns an empty list when no attachment is present', function (): void {
    assert_same([], SupportAttachmentFiles::normalize([]));
});
