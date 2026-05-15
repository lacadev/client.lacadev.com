<?php

namespace App\Assets;

/**
 * Builds login-screen data without mixing option parsing into enqueue logic.
 */
final class LoginAssetData
{
    public static function resolveLogoUrl(mixed $rawValue, ?callable $attachmentUrlResolver = null): string
    {
        if (empty($rawValue)) {
            return '';
        }

        $attachmentUrlResolver ??= static function (int $attachmentId): string {
            $url = wp_get_attachment_image_url($attachmentId, 'full');
            return $url ?: '';
        };

        if (is_numeric($rawValue)) {
            return (string) $attachmentUrlResolver((int) $rawValue);
        }

        if (is_array($rawValue)) {
            if (!empty($rawValue['url']) && is_string($rawValue['url'])) {
                return esc_url_raw($rawValue['url']);
            }

            foreach (['id', 'value'] as $key) {
                if (!empty($rawValue[$key]) && is_numeric($rawValue[$key])) {
                    return (string) $attachmentUrlResolver((int) $rawValue[$key]);
                }
            }

            return '';
        }

        if (is_string($rawValue)) {
            if (filter_var($rawValue, FILTER_VALIDATE_URL)) {
                return esc_url_raw($rawValue);
            }

            if (ctype_digit($rawValue)) {
                return (string) $attachmentUrlResolver((int) $rawValue);
            }
        }

        return '';
    }

    public static function buildLocales(callable $optionReader): array
    {
        return [
            'vi' => [
                'userLabel' => self::pick($optionReader, 'login_user_label', 'vi', 'Ai đang ghé trạm?'),
                'userPlaceholder' => self::pick($optionReader, 'login_user_placeholder', 'vi', 'Điền tên hoặc email vào đây nhé'),
                'passLabel' => self::pick($optionReader, 'login_password_label', 'vi', 'Chìa khóa'),
                'passPlaceholder' => self::pick($optionReader, 'login_password_placeholder', 'vi', 'Nhập chìa khóa mở cửa'),
                'welcomeText' => nl2br(sanitize_textarea_field(self::pick(
                    $optionReader,
                    'login_welcome_text',
                    'vi',
                    "Chào mừng về Trạm Laca!\nCắm sạc, pha trà và bắt đầu nào!"
                ))),
                'forgetPwd' => self::pick($optionReader, 'login_forgot_label', 'vi', 'Rớt chìa khoá?'),
                'backToBlog' => self::pick($optionReader, 'login_back_label', 'vi', '← Rời khỏi Trạm'),
            ],
            'en' => [
                'userLabel' => self::pick($optionReader, 'login_user_label', 'en', "Who's visiting the station?"),
                'userPlaceholder' => self::pick($optionReader, 'login_user_placeholder', 'en', 'Enter name or email here'),
                'passLabel' => self::pick($optionReader, 'login_password_label', 'en', 'The Key'),
                'passPlaceholder' => self::pick($optionReader, 'login_password_placeholder', 'en', 'Enter your key to open'),
                'welcomeText' => nl2br(sanitize_textarea_field(self::pick(
                    $optionReader,
                    'login_welcome_text',
                    'en',
                    "Welcome to Laca Station!\nCharge up, brew some tea and let's go!"
                ))),
                'forgetPwd' => self::pick($optionReader, 'login_forgot_label', 'en', 'Lost your key?'),
                'backToBlog' => self::pick($optionReader, 'login_back_label', 'en', '← Leave the Station'),
            ],
        ];
    }

    public static function buildPayload(string $logoUrl, array $locales, string $language, string $homeUrl): array
    {
        $loginVi = $locales['vi'] ?? [];

        return [
            'logoUrl' => $logoUrl,
            'locales' => $locales,
            'userLabel' => $loginVi['userLabel'] ?? '',
            'userPlaceholder' => $loginVi['userPlaceholder'] ?? '',
            'passLabel' => $loginVi['passLabel'] ?? '',
            'passPlaceholder' => $loginVi['passPlaceholder'] ?? '',
            'welcomeText' => $loginVi['welcomeText'] ?? '',
            'forgetPwd' => $loginVi['forgetPwd'] ?? '',
            'backToBlog' => $loginVi['backToBlog'] ?? '',
            'language' => $language,
            'homeUrl' => $homeUrl,
        ];
    }

    private static function pick(callable $optionReader, string $key, string $lang, string $fallback): string
    {
        $value = $optionReader("{$key}_{$lang}");
        if (empty($value)) {
            $value = $optionReader($key);
        }

        return !empty($value) ? (string) $value : $fallback;
    }
}
