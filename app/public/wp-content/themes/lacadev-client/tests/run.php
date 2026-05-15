<?php

declare(strict_types=1);

$themeRoot = dirname(__DIR__);

function app_class_file(string $class): string|false
{
    global $themeRoot;

    if (!str_starts_with($class, 'App\\')) {
        return false;
    }

    $relative = str_replace('\\', '/', substr($class, 4)) . '.php';
    $file = $themeRoot . '/app/src/' . $relative;

    return is_file($file) ? $file : false;
}

spl_autoload_register(static function (string $class): void {
    $file = app_class_file($class);
    if ($file !== false) {
        require $file;
    }
});

$tests = [];

function test(string $name, callable $callback): void
{
    global $tests;
    $tests[] = [$name, $callback];
}

function assert_true(bool $condition, string $message = 'Failed asserting that condition is true.'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $message = $message ?: sprintf(
            'Failed asserting that %s is identical to %s.',
            var_export($actual, true),
            var_export($expected, true)
        );

        throw new RuntimeException($message);
    }
}

foreach (glob(__DIR__ . '/Unit/*Test.php') ?: [] as $testFile) {
    require $testFile;
}

$failures = 0;

foreach ($tests as [$name, $callback]) {
    try {
        $callback();
        echo ".";
    } catch (Throwable $exception) {
        $failures++;
        echo "F\n";
        echo $name . "\n";
        echo $exception->getMessage() . "\n";
    }
}

echo "\n" . count($tests) . " tests, {$failures} failures\n";

exit($failures > 0 ? 1 : 0);
