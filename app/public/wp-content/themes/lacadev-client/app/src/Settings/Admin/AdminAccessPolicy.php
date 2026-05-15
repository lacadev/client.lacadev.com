<?php

namespace App\Settings\Admin;

/**
 * Static admin access/menu policy definitions.
 */
final class AdminAccessPolicy
{
    public static function deniedPluginScreens(bool $hideThemeEditor): array
    {
        $screens = [
            'plugins',
            'plugin-install',
            'plugin-editor',
            'themes',
            'theme-install',
            'theme-install',
            'customize',
            'customize',
            'tools',
            'import',
            'export',
            'tools_page_action-scheduler',
            'tools_page_export_personal_data',
            'tools_page_export_personal_data',
            'tools_page_remove_personal_data',
        ];

        if ($hideThemeEditor) {
            $screens[] = 'theme-editor';
        }

        return $screens;
    }

    public static function removedSettingsPages(): array
    {
        return [
            'options-reading.php',
            'options-writing.php',
            'options-discussion.php',
            'options-media.php',
            'privacy.php',
            'options-permalink.php',
            'tinymce-advanced',
        ];
    }

    public static function deniedSettingsScreens(): array
    {
        return [
            'options-reading',
            'options-writing',
            'options-discussion',
            'options-media',
            'privacy',
            'options-permalink',
            'settings_page_tinymce-advanced',
            'toplevel_page_wpseo_dashboard',
        ];
    }

    public static function hiddenMenuSlugs(bool $hideComments): array
    {
        $hiddenMenus = [
            'tools.php',
            'wpseo_dashboard',
            'duplicator',
            'yit_plugin_panel',
            'woocommerce-checkout-manager',
        ];

        if ($hideComments) {
            $hiddenMenus[] = 'edit-comments.php';
        }

        return $hiddenMenus;
    }
}
