<?php

namespace App\Settings\Tracker;

/**
 * Builds normalized tracker payloads for public support requests.
 */
final class ClientSupportRequestBuilder
{
    private const DEFAULT_TYPE = 'request';

    private const TYPE_LABELS = [
        'request' => 'Yêu cầu hỗ trợ',
        'bug' => 'Báo lỗi',
        'content' => 'Nội dung cần cập nhật',
        'maintenance' => 'Bảo trì',
        'billing' => 'Thanh toán',
    ];

    public static function rateLimitKey(string $clientIp): string
    {
        return 'laca_client_request_' . md5($clientIp);
    }

    public static function normalizeType(string $requestType): string
    {
        $requestType = sanitize_key($requestType);

        return array_key_exists($requestType, self::TYPE_LABELS)
            ? $requestType
            : self::DEFAULT_TYPE;
    }

    public static function build(array $input): array
    {
        $requestType = self::normalizeType((string) ($input['request_type'] ?? self::DEFAULT_TYPE));
        $requestId = (string) ($input['request_id'] ?? '');
        $message = trim((string) ($input['message'] ?? ''));
        $siteUrl = (string) ($input['site_url'] ?? '');
        $contactName = sanitize_text_field((string) ($input['contact_name'] ?? ''));
        $contactEmail = sanitize_text_field((string) ($input['contact_email'] ?? ''));
        $pageUrl = sanitize_text_field((string) ($input['page_url'] ?? ''));
        $clientIp = sanitize_text_field((string) ($input['ip'] ?? ''));
        $userAgent = sanitize_text_field((string) ($input['user_agent'] ?? ''));
        $attachments = array_values(array_filter(
            (array) ($input['attachments'] ?? []),
            static fn(mixed $attachment): bool => is_array($attachment)
        ));

        $parts = [
            'Mã yêu cầu: ' . $requestId,
            '[' . (self::TYPE_LABELS[$requestType] ?? self::TYPE_LABELS[self::DEFAULT_TYPE]) . ']',
            'Website: ' . $siteUrl,
            $message,
        ];

        if ($contactName !== '') {
            $parts[] = 'Người gửi: ' . $contactName;
        }

        if ($contactEmail !== '') {
            $parts[] = 'Email: ' . $contactEmail;
        }

        if ($pageUrl !== '') {
            $parts[] = 'Trang gửi: ' . $pageUrl;
        }

        if (!empty($attachments)) {
            $parts[] = 'Đính kèm:';
            foreach ($attachments as $attachment) {
                $url = (string) ($attachment['url'] ?? '');
                if ($url !== '') {
                    $parts[] = '- ' . $url;
                }
            }
        }

        $context = [
            'request_id' => $requestId,
            'request_type' => $requestType,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'page_url' => $pageUrl,
            'ip' => $clientIp,
            'user_agent' => $userAgent,
            'attachments' => $attachments,
        ];

        return [
            'request_type' => $requestType,
            'content' => implode("\n", $parts),
            'context' => $context,
            'log' => [
                'type' => 'client_request',
                'content' => implode("\n", $parts),
                'level' => $requestType === 'bug' ? 'warning' : 'info',
                'request_type' => $requestType,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'request_id' => $requestId,
                'attachments' => $attachments,
                'meta' => $context,
            ],
        ];
    }
}
