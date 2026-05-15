<?php

namespace App\Settings\Admin;

/**
 * Small helpers for admin media upload behavior.
 */
final class AdminMediaSupport
{
    public static function allowedMimes(array $mimes): array
    {
        return array_merge($mimes, [
            'ac3' => 'audio/ac3',
            'mpa' => 'audio/MPA',
            'flv' => 'video/x-flv',
            'svg' => 'image/svg+xml',
        ]);
    }

    public static function isHelpGuideScreen(string $hookSuffix, string $page, string $screenId): bool
    {
        return strpos($hookSuffix, 'laca-help-content-settings') !== false
            || strpos($screenId, 'laca-help-content-settings') !== false
            || $page === 'laca-help-content-settings'
            || strpos($hookSuffix, 'laca-management-settings') !== false
            || strpos($screenId, 'laca-management-settings') !== false
            || $page === 'laca-management-settings'
            || ($page !== '' && strpos($page, 'management-settings') !== false)
            || strpos($hookSuffix, 'lacadev-help') !== false
            || $page === 'lacadev-help';
    }

    public static function pasteImageConfig(string $ajaxUrl, string $nonce, callable $translate): array
    {
        return [
            'ajaxUrl' => $ajaxUrl,
            'nonce' => $nonce,
            'i18n' => [
                'uploadFail' => $translate('Không thể upload ảnh từ clipboard. Vui lòng thử lại.'),
            ],
        ];
    }

    public static function sanitizeUploadFilename(string $filename, string $timestamp): string
    {
        $info = pathinfo($filename);
        $ext = empty($info['extension']) ? '' : '.' . $info['extension'];
        $newFileName = str_replace($ext, '', $timestamp . '-' . $filename);

        foreach (self::vietnameseMap() as $nonUnicode => $unicodePattern) {
            $newFileName = preg_replace("/({$unicodePattern})/i", $nonUnicode, $newFileName);
        }

        $newFileName = str_replace(' ', '-', (string) $newFileName);
        $newFileName = preg_replace('/[^A-Za-z0-9\-]/', '', $newFileName);
        $newFileName = preg_replace('/-+/', '-', (string) $newFileName);

        return $newFileName . $ext;
    }

    private static function vietnameseMap(): array
    {
        return [
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
            'A' => 'Á|À|Ả|Ã|Ạ|Ă|Ắ|Ặ|Ằ|Ẳ|Ẵ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
            'D' => 'Đ',
            'E' => 'É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
            'I' => 'Í|Ì|Ỉ|Ĩ|Ị',
            'O' => 'Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ỗ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
            'U' => 'Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
            'Y' => 'Ý|Ỳ|Ỷ|Ỹ|Ỵ',
        ];
    }
}
