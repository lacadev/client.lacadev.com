<?php

namespace App\Settings\Tracker;

/**
 * Sends tracker payloads and normalizes WordPress HTTP responses.
 */
final class TrackerHttpTransport
{
    public static function post(string $endpoint, array $payload, bool $blocking = true): array
    {
        return self::postWithCallbacks(
            $endpoint,
            $payload,
            $blocking,
            static fn(string $url, array $args): mixed => wp_remote_post($url, $args),
            static fn(mixed $response): bool => is_wp_error($response),
            static fn(mixed $response): string => $response->get_error_message(),
            static fn(mixed $response): int => wp_remote_retrieve_response_code($response),
            static fn(mixed $response): string => (string) wp_remote_retrieve_body($response)
        );
    }

    public static function postWithCallbacks(
        string $endpoint,
        array $payload,
        bool $blocking,
        callable $remotePost,
        callable $isError,
        callable $errorMessage,
        callable $responseCode,
        callable $responseBody
    ): array {
        $response = $remotePost($endpoint, [
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => $blocking ? 15 : 8,
            'blocking' => $blocking,
        ]);

        if (!$blocking) {
            return [
                'success' => !$isError($response),
                'code' => null,
                'error' => $isError($response) ? $errorMessage($response) : '',
            ];
        }

        if ($isError($response)) {
            return [
                'success' => false,
                'code' => null,
                'error' => $errorMessage($response),
            ];
        }

        $code = (int) $responseCode($response);
        $body = trim((string) $responseBody($response));

        if ($code < 200 || $code >= 300) {
            $decoded = json_decode($body, true);
            $message = is_array($decoded) && !empty($decoded['message'])
                ? (string) $decoded['message']
                : 'HTTP ' . $code;

            return [
                'success' => false,
                'code' => $code,
                'error' => $message,
            ];
        }

        return [
            'success' => true,
            'code' => $code,
            'error' => '',
        ];
    }
}
