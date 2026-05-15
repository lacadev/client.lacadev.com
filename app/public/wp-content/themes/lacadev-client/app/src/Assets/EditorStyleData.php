<?php

namespace App\Assets;

/**
 * Inline editor CSS generated from theme options.
 */
final class EditorStyleData
{
    public static function cssVariables(array $colors): string
    {
        $primaryColor = (string) ($colors['primary_color'] ?? '');
        $secondaryColor = (string) ($colors['secondary_color'] ?? '');
        $bgColor = (string) ($colors['bg_color'] ?? '');
        $primaryColorDark = (string) ($colors['primary_color_dark'] ?? '');
        $secondaryColorDark = (string) ($colors['secondary_color_dark'] ?? '');
        $bgColorDark = (string) ($colors['bg_color_dark'] ?? '');

        return <<<CSS
        :root, .editor-styles-wrapper {
            --primary-color: {$primaryColor};
            --secondary-color: {$secondaryColor};
            --bg-color: {$bgColor};
            --primary-color-dark: {$primaryColorDark};
            --secondary-color-dark: {$secondaryColorDark};
            --bg-color-dark: {$bgColorDark};
            font-family: 'Quicksand', sans-serif !important;
        }
    CSS;
    }
}
