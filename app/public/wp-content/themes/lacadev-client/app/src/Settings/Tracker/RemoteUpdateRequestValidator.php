<?php

namespace App\Settings\Tracker;

/**
 * Validates and normalizes incoming remote-update requests.
 */
final class RemoteUpdateRequestValidator
{
    private const ALLOWED_ACTIONS = ['update_plugin', 'update_theme', 'update_core'];

    public static function validate(array $params, string $expectedSecret): array
    {
        $secretKey = sanitize_text_field((string) ($params['secret_key'] ?? ''));
        $action = sanitize_key((string) ($params['action'] ?? ''));
        $slug = sanitize_text_field((string) ($params['slug'] ?? ''));

        if ($secretKey === '' || $secretKey !== $expectedSecret) {
            return [
                'ok' => false,
                'status' => 401,
                'message' => 'Unauthorized',
            ];
        }

        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Action không hợp lệ.',
            ];
        }

        if ($action === 'update_plugin' && $slug === '') {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Thiếu slug plugin.',
            ];
        }

        if ($action === 'update_theme' && $slug === '') {
            return [
                'ok' => false,
                'status' => 400,
                'message' => 'Thiếu slug theme.',
            ];
        }

        return [
            'ok' => true,
            'action' => $action,
            'slug' => $slug,
            'dry_run' => !empty($params['dry_run']),
            'params' => $params,
        ];
    }
}
