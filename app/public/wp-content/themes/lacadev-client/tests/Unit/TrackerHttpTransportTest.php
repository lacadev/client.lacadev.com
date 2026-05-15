<?php

declare(strict_types=1);

use App\Settings\Tracker\TrackerHttpTransport;

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($data, $flags, $depth);
    }
}

test('TrackerHttpTransport sends JSON payloads and normalizes 2xx responses', function (): void {
    $capturedUrl = '';
    $capturedArgs = [];

    $result = TrackerHttpTransport::postWithCallbacks(
        'https://lacadev.test/tracker',
        ['hello' => 'world'],
        true,
        static function (string $url, array $args) use (&$capturedUrl, &$capturedArgs): array {
            $capturedUrl = $url;
            $capturedArgs = $args;

            return ['code' => 201, 'body' => '{"ok":true}'];
        },
        static fn(mixed $response): bool => false,
        static fn(mixed $response): string => '',
        static fn(array $response): int => (int) $response['code'],
        static fn(array $response): string => (string) $response['body']
    );

    assert_true($result['success']);
    assert_same(201, $result['code']);
    assert_same('', $result['error']);
    assert_same('https://lacadev.test/tracker', $capturedUrl);
    assert_same('{"hello":"world"}', $capturedArgs['body']);
    assert_same(['Content-Type' => 'application/json'], $capturedArgs['headers']);
    assert_same(15, $capturedArgs['timeout']);
    assert_true($capturedArgs['blocking']);
});

test('TrackerHttpTransport returns JSON message for non-2xx responses', function (): void {
    $result = TrackerHttpTransport::postWithCallbacks(
        'https://lacadev.test/tracker',
        ['event' => 'deploy'],
        true,
        static fn(string $url, array $args): array => ['code' => 422, 'body' => '{"message":"Bad payload"}'],
        static fn(mixed $response): bool => false,
        static fn(mixed $response): string => '',
        static fn(array $response): int => (int) $response['code'],
        static fn(array $response): string => (string) $response['body']
    );

    assert_true(!$result['success']);
    assert_same(422, $result['code']);
    assert_same('Bad payload', $result['error']);
});

test('TrackerHttpTransport normalizes non-blocking success and error responses', function (): void {
    $success = TrackerHttpTransport::postWithCallbacks(
        'https://lacadev.test/tracker',
        ['event' => 'heartbeat'],
        false,
        static fn(string $url, array $args): array => ['queued' => true],
        static fn(mixed $response): bool => false,
        static fn(mixed $response): string => '',
        static fn(mixed $response): int => 0,
        static fn(mixed $response): string => ''
    );

    assert_true($success['success']);
    assert_same(null, $success['code']);
    assert_same('', $success['error']);

    $error = TrackerHttpTransport::postWithCallbacks(
        'https://lacadev.test/tracker',
        ['event' => 'heartbeat'],
        false,
        static fn(string $url, array $args): array => ['error' => 'Connection timeout'],
        static fn(array $response): bool => !empty($response['error']),
        static fn(array $response): string => (string) $response['error'],
        static fn(mixed $response): int => 0,
        static fn(mixed $response): string => ''
    );

    assert_true(!$error['success']);
    assert_same(null, $error['code']);
    assert_same('Connection timeout', $error['error']);
});
