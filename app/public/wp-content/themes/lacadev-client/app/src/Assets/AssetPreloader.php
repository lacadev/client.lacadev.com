<?php

namespace App\Assets;

/**
 * Preload and critical CSS output helpers.
 */
final class AssetPreloader
{
    public static function fontPaths(): array
    {
        return [
            'fonts/BeVietnamPro-Regular.bbe77399f9.ttf',
            'fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf',
            'fonts/Quicksand-Regular.61504eaec8.ttf',
        ];
    }

    public static function fontPreloadTags(string $distUrl): string
    {
        $html = '';

        foreach (self::fontPaths() as $font) {
            $html .= '<link rel="preload" href="' . esc_url($distUrl . $font) . '" as="font" type="font/ttf" crossorigin>' . "\n";
        }

        return $html;
    }

    public static function frontendPreloadHtml(
        string $distPath,
        string $distUrl,
        bool $allowCriticalInline = true,
        ?callable $fileExists = null,
        ?callable $fileGetContents = null
    ): string {
        $fileExists ??= 'file_exists';
        $fileGetContents ??= 'file_get_contents';

        $html = '';
        $criticalPath = $distPath . 'styles/critical.css';

        if ($allowCriticalInline && $fileExists($criticalPath)) {
            $criticalCss = $fileGetContents($criticalPath);
            if ($criticalCss) {
                $html .= '<style id="critical-css">' . $criticalCss . '</style>' . "\n";
            }
        }

        if ($fileExists($distPath . 'critical.js')) {
            $html .= '<link rel="preload" href="' . esc_url($distUrl . 'critical.js') . '" as="script">' . "\n";
        }

        if (!$allowCriticalInline || !$fileExists($criticalPath)) {
            $html .= '<link rel="preload" href="' . esc_url($distUrl . 'styles/theme.css') . '" as="style">' . "\n";
        }

        return $html . self::fontPreloadTags($distUrl);
    }

    public static function printAdminPreloads(): void
    {
        $themeRootUri = dirname(get_stylesheet_directory_uri());
        echo self::fontPreloadTags($themeRootUri . '/dist/');
    }

    public static function printFrontendPreloads(): void
    {
        $themeRootDir = dirname(get_stylesheet_directory());
        $themeRootUri = dirname(get_stylesheet_directory_uri());
        $allowCriticalInline = wp_get_environment_type() !== 'local';

        echo self::frontendPreloadHtml(
            $themeRootDir . '/dist/',
            $themeRootUri . '/dist/',
            $allowCriticalInline
        );
    }
}
