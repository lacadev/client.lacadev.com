<?php

namespace App\Assets;

/**
 * Inline assets that adapt the WordPress login screen to theme settings.
 */
final class LoginInlineAssets
{
    public static function placeholderScript(): string
    {
        return <<<'JS'
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        var cfg = window.loginI18n || {};
        var locales = cfg.locales || {};
        var lang = (document.documentElement.lang || '').indexOf('en') !== -1 ? 'en' : 'vi';
        var data = locales[lang] || locales.vi || {};
        var userPlaceholder = data.userPlaceholder || cfg.userPlaceholder || '';
        var passPlaceholder = data.passPlaceholder || cfg.passPlaceholder || '';
        var user = document.getElementById('user_login');
        var pass = document.getElementById('user_pass');

        if (user && userPlaceholder) {
            user.setAttribute('placeholder', userPlaceholder);
        }

        if (pass && passPlaceholder) {
            pass.setAttribute('placeholder', passPlaceholder);
        }
    });
}());
JS;
    }

    public static function logoCss(string $logoUrl): string
    {
        return "#login h1 a{background-image:url('" . esc_url_raw($logoUrl) . "') !important;}";
    }
}
