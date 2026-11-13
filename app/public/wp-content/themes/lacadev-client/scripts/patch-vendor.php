<?php
/**
 * scripts/patch-vendor.php
 *
 * Vá cảnh báo deprecated PHP 8.2+ trong vendor/htmlburger/wpemerge-theme-core:
 * "Using ${var} in strings is deprecated"
 */

$themeDir = dirname(__DIR__);
$targetFile = $themeDir . '/vendor/htmlburger/wpemerge-theme-core/src/Theme/Theme.php';

if (!file_exists($targetFile)) {
    echo "Target file not found: {$targetFile}\n";
    exit(0);
}

$content = file_get_contents($targetFile);
$search1 = '"partials/${partial}-${child}.php"';
$search2 = '"partials/${partial}.php"';

$replace1 = '"partials/{$partial}-{$child}.php"';
$replace2 = '"partials/{$partial}.php"';

if (str_contains($content, $replace1) && str_contains($content, $replace2)) {
    echo "already patched\n";
} elseif (str_contains($content, $search1) || str_contains($content, $search2)) {
    $content = str_replace([$search1, $search2], [$replace1, $replace2], $content);
    file_put_contents($targetFile, $content);
    echo "patched successfully\n";
} else {
    echo "Warning: Neither original strings nor patched strings found in {$targetFile}\n";
    exit(1);
}

// Quét toàn bộ vendor/htmlburger/**/*.php tìm mẫu "${" trong chuỗi nháy kép
$vendorHtmlBurger = $themeDir . '/vendor/htmlburger';
if (is_dir($vendorHtmlBurger)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorHtmlBurger, FilesystemIterator::SKIP_DOTS));
    $remaining = [];
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $code = file_get_contents($file->getPathname());
            if (preg_match('/"\s*[^"]*\$\{[a-zA-Z0-9_]+[^"]*"/m', $code, $matches)) {
                $remaining[] = str_replace($themeDir . '/', '', $file->getPathname());
            }
        }
    }
    if (!empty($remaining)) {
        echo "Found remaining '\${' patterns in:\n - " . implode("\n - ", $remaining) . "\n";
    } else {
        echo "Scan vendor/htmlburger: zero remaining '\${' in double quotes.\n";
    }
}

