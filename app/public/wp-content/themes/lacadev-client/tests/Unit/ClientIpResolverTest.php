<?php

declare(strict_types=1);

use App\Support\ClientIpResolver;

test('ClientIpResolver prefers Cloudflare IP before forwarded and remote addresses', function (): void {
    assert_same('203.0.113.10', ClientIpResolver::fromServer([
        'HTTP_CF_CONNECTING_IP' => '203.0.113.10',
        'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
        'REMOTE_ADDR' => '192.0.2.30',
    ], 'unknown'));
});

test('ClientIpResolver reads the first valid forwarded IP', function (): void {
    assert_same('198.51.100.20', ClientIpResolver::fromServer([
        'HTTP_X_FORWARDED_FOR' => '198.51.100.20, 192.0.2.30',
    ], 'unknown'));
});

test('ClientIpResolver falls back when server values are missing or invalid', function (): void {
    assert_same('unknown', ClientIpResolver::fromServer([
        'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
        'REMOTE_ADDR' => '',
    ], 'unknown'));
});
