<?php

declare(strict_types=1);

use App\Settings\Tracker\TrackerFileIntegrity;

test('TrackerFileIntegrity records current baseline without changes on first run', function (): void {
    $result = TrackerFileIntegrity::compare(
        [],
        ['/theme/functions.php' => 'themes/client/functions.php'],
        static fn(string $path): bool => true,
        static fn(string $path): int => 100,
        static fn(int $mtime): string => 'time-' . $mtime
    );

    assert_same(['themes/client/functions.php' => 100], $result['current']);
    assert_same([], $result['changed']);
});

test('TrackerFileIntegrity reports modified and new files after baseline exists', function (): void {
    $result = TrackerFileIntegrity::compare(
        ['themes/client/functions.php' => 100],
        [
            '/theme/functions.php' => 'themes/client/functions.php',
            '/theme/style.css' => 'themes/client/style.css',
        ],
        static fn(string $path): bool => true,
        static fn(string $path): int => $path === '/theme/functions.php' ? 200 : 300,
        static fn(int $mtime): string => 'time-' . $mtime
    );

    assert_same([
        'themes/client/functions.php (sửa lúc time-200)',
        'themes/client/style.css [mới] (tạo lúc time-300)',
    ], $result['changed']);
});

test('TrackerFileIntegrity skips missing watched files', function (): void {
    $result = TrackerFileIntegrity::compare(
        ['themes/client/missing.php' => 100],
        ['/theme/missing.php' => 'themes/client/missing.php'],
        static fn(string $path): bool => false,
        static fn(string $path): int => 200,
        static fn(int $mtime): string => 'time-' . $mtime
    );

    assert_same([], $result['current']);
    assert_same([], $result['changed']);
});
