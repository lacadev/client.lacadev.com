<?php

declare(strict_types=1);

use App\Settings\Admin\AdminMediaSupport;

test('AdminMediaSupport adds supported custom upload mime types', function (): void {
    $mimes = AdminMediaSupport::allowedMimes(['jpg' => 'image/jpeg']);

    assert_same('image/jpeg', $mimes['jpg']);
    assert_same('audio/ac3', $mimes['ac3']);
    assert_same('video/x-flv', $mimes['flv']);
    assert_same('image/svg+xml', $mimes['svg']);
});

test('AdminMediaSupport detects help guide admin screens', function (): void {
    assert_true(AdminMediaSupport::isHelpGuideScreen('toplevel_page_laca-help-content-settings', '', ''));
    assert_true(AdminMediaSupport::isHelpGuideScreen('', 'laca-management-settings', ''));
    assert_true(AdminMediaSupport::isHelpGuideScreen('', 'client-management-settings-extra', ''));
    assert_true(AdminMediaSupport::isHelpGuideScreen('admin_page_lacadev-help', '', ''));
    assert_true(!AdminMediaSupport::isHelpGuideScreen('dashboard', 'index.php', 'dashboard'));
});

test('AdminMediaSupport builds paste-image script config', function (): void {
    $config = AdminMediaSupport::pasteImageConfig(
        'https://example.test/wp-admin/admin-ajax.php',
        'nonce-value',
        static fn(string $text): string => 't:' . $text
    );

    assert_same('https://example.test/wp-admin/admin-ajax.php', $config['ajaxUrl']);
    assert_same('nonce-value', $config['nonce']);
    assert_same('t:Không thể upload ảnh từ clipboard. Vui lòng thử lại.', $config['i18n']['uploadFail']);
});

test('AdminMediaSupport normalizes uploaded filenames', function (): void {
    assert_same(
        '202605151030-Dang-test-file.png',
        AdminMediaSupport::sanitizeUploadFilename('Đặng test file.png', '202605151030')
    );

    assert_same(
        '202605151030-my-file.PDF',
        AdminMediaSupport::sanitizeUploadFilename('my   file.PDF', '202605151030')
    );
});
