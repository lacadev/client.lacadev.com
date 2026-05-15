<?php

namespace App\Settings\Tracker;

/**
 * Finds suspicious files in high-risk WordPress directories.
 */
final class SuspiciousFileScanner
{
    private const SUSPICIOUS_EXTS = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar'];

    private const ROOT_WATCH_FILES = ['wp-config.php', '.htaccess', 'index.php', '.user.ini', 'php.ini'];

    private const ALLOWED_ROOT_FILES = [
        'wp-config.php',
        'wp-config-sample.php',
        '.htaccess',
        'index.php',
        'wp-activate.php',
        'wp-blog-header.php',
        'wp-comments-post.php',
        'wp-cron.php',
        'wp-links-opml.php',
        'wp-load.php',
        'wp-login.php',
        'wp-mail.php',
        'wp-settings.php',
        'wp-signup.php',
        'wp-trackback.php',
        'xmlrpc.php',
        'readme.html',
        'license.txt',
        '.user.ini',
        'php.ini',
        'robots.txt',
        'sitemap.xml',
        'sitemap_index.xml',
    ];

    private const SHELL_PATTERNS = [
        'eval(base64_decode',
        'eval(gzinflate',
        'eval(str_rot13',
        'eval($_POST',
        'eval($_GET',
        'assert($_',
        'system($_',
        'passthru($_',
        'exec($_',
        'shell_exec($_',
        'base64_decode(str_rot13',
        'preg_replace(\'/.*/e\'',
        'FilesMan',
        'c99shell',
        'r57shell',
    ];

    public static function scan(string $rootDir, array $relativeDirs): array
    {
        $found = [];
        $rootDir = rtrim($rootDir, '/');

        foreach ($relativeDirs as $relDir) {
            $absDir = $rootDir . ($relDir ? '/' . ltrim($relDir, '/') : '');
            if (!is_dir($absDir)) {
                continue;
            }

            if ($relDir === '') {
                self::scanRootLevel($absDir, $rootDir, $found);
            } else {
                self::scanSuspiciousRecursive($absDir, trim($relDir, '/') . '/', $found);
            }
        }

        return $found;
    }

    private static function scanRootLevel(string $absDir, string $rootDir, array &$found): void
    {
        foreach (self::ROOT_WATCH_FILES as $file) {
            $full = $absDir . '/' . $file;
            if (!file_exists($full)) {
                continue;
            }

            if (in_array($file, ['wp-config.php', '.htaccess'], true)) {
                self::checkFileForShellPatterns($full, $file, $found);
            }
        }

        $files = glob($absDir . '/*.php') ?: [];
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            if (!in_array($filename, self::ALLOWED_ROOT_FILES, true)) {
                $found[] = self::relativeRootPath($rootDir, $filePath) . ' [PHP lạ ở root]';
            }
        }

        $htmlFiles = array_merge(
            glob($absDir . '/*.html') ?: [],
            glob($absDir . '/*.htm') ?: [],
            glob($absDir . '/*.js') ?: []
        );

        foreach ($htmlFiles as $filePath) {
            $filename = basename($filePath);
            if (!in_array($filename, ['readme.html', 'license.txt'], true)) {
                $found[] = self::relativeRootPath($rootDir, $filePath) . ' [file lạ ở root]';
            }
        }
    }

    private static function scanSuspiciousRecursive(string $absDir, string $relPrefix, array &$found): void
    {
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($it as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $ext = strtolower($file->getExtension());
                if (in_array($ext, self::SUSPICIOUS_EXTS, true)) {
                    $found[] = $relPrefix . $it->getSubPathname();
                }
            }
        } catch (\UnexpectedValueException) {
            // Permission denied or unreadable directory.
        }
    }

    private static function checkFileForShellPatterns(string $filePath, string $displayName, array &$found): void
    {
        $content = @file_get_contents($filePath, false, null, 0, 51200);
        if ($content === false) {
            return;
        }

        foreach (self::SHELL_PATTERNS as $pattern) {
            if (stripos($content, $pattern) !== false) {
                $found[] = $displayName . " [⚠️ pattern đáng ngờ: '{$pattern}']";
                break;
            }
        }
    }

    private static function relativeRootPath(string $rootDir, string $filePath): string
    {
        return '/' . ltrim(str_replace(rtrim($rootDir, '/') . '/', '', $filePath), '/');
    }
}
