<?php

namespace App\Settings\Tracker;

use App\Support\ClientIpResolver;

/**
 * Handles public support-request intake for client sites.
 */
final class TrackerClientRequestHandler
{
    public static function handle(
        \WP_REST_Request $request,
        callable $isConfigured,
        callable $hasTrackerEventTable,
        callable $sendLogs
    ): \WP_REST_Response {
        if (!$isConfigured()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Tracker chưa được cấu hình.',
            ], 503);
        }

        $message = trim((string) $request->get_param('message'));
        if ($message === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Vui lòng nhập nội dung yêu cầu.',
            ], 400);
        }

        $clientIp = ClientIpResolver::fromGlobals('unknown');
        $rateKey = ClientSupportRequestBuilder::rateLimitKey($clientIp);
        if (get_transient($rateKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Bạn vừa gửi yêu cầu. Vui lòng thử lại sau ít phút.',
            ], 429);
        }

        $requestType = ClientSupportRequestBuilder::normalizeType((string) ($request->get_param('request_type') ?: 'request'));
        $requestId = strtoupper(substr(str_replace('-', '', wp_generate_uuid4()), 0, 10));
        $attachments = SupportAttachmentUploader::upload($request->get_file_params(), $requestId);

        if (is_wp_error($attachments)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $attachments->get_error_message(),
            ], 400);
        }

        $payload = ClientSupportRequestBuilder::build([
            'request_id' => $requestId,
            'request_type' => $requestType,
            'message' => $message,
            'site_url' => home_url('/'),
            'contact_name' => sanitize_text_field((string) $request->get_param('contact_name')),
            'contact_email' => sanitize_email((string) $request->get_param('contact_email')),
            'page_url' => esc_url_raw((string) $request->get_param('page_url')),
            'ip' => $clientIp,
            'user_agent' => sanitize_text_field((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'attachments' => $attachments,
        ]);

        $canQueueLocally = $hasTrackerEventTable();
        $sent = $sendLogs([$payload['log']], true, 'support', $payload['context']);

        if (!$sent) {
            if ($canQueueLocally) {
                set_transient($rateKey, 1, 5 * MINUTE_IN_SECONDS);

                return new \WP_REST_Response([
                    'success' => true,
                    'queued' => true,
                    'message' => 'Yêu cầu đã được ghi nhận. Hệ thống sẽ tự gửi lại khi kết nối ổn định. Mã yêu cầu: ' . $requestId,
                    'request_id' => $requestId,
                ], 202);
            }

            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Không gửi được yêu cầu tới hệ thống LacaDev. Vui lòng thử lại sau.',
            ], 502);
        }

        set_transient($rateKey, 1, 5 * MINUTE_IN_SECONDS);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Yêu cầu đã được gửi. Mã yêu cầu: ' . $requestId,
            'request_id' => $requestId,
        ], 201);
    }
}
