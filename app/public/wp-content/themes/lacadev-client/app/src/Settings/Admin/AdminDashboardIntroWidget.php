<?php

namespace App\Settings\Admin;

/**
 * Renders the small author/contact dashboard intro widget.
 */
final class AdminDashboardIntroWidget
{
    public static function html(array $author, string $logoUrl): string
    {
        $phoneHref = str_replace(['.', ',', ' '], '', (string) ($author['phone_number'] ?? ''));

        return sprintf(
            '<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 0;">
                <a target="_blank" href="%1$s" title="%2$s" style="opacity: 0.9; transition: opacity 0.2s;">
                    <img style="max-width: 160px; height: auto; display: block;" src="%3$s" alt="%2$s">
                </a>
                <div style="margin-top: 20px; text-align: center;">
                    <p style="margin: 0 0 15px; font-size: 16px; font-style: italic; color: #b5b5b5; font-family: \'Quicksand\', sans-serif; font-weight: 500;">"Coding amidst the journeys"</p>
                    <div style="display: flex; gap: 12px; justify-content: center; align-items: center; font-size: 14px; color: #848383; font-family: \'Quicksand\', sans-serif; font-weight: 600;">
                        <a style="color: inherit; text-decoration: none;" href="tel:%4$s" target="_blank">%5$s</a>
                        <span style="color: #dcdcde;">|</span>
                        <a style="color: inherit; text-decoration: none;" href="mailto:%6$s" target="_blank">%6$s</a>
                        <span style="color: #dcdcde;">|</span>
                        <a style="color: inherit; text-decoration: none;" href="%1$s" target="_blank">Ghé thăm tôi</a>
                    </div>
                </div>
            </div>',
            esc_url($author['website'] ?? ''),
            esc_attr($author['name'] ?? ''),
            esc_url($logoUrl),
            esc_attr($phoneHref),
            esc_html($author['phone_number'] ?? ''),
            esc_attr($author['email'] ?? '')
        );
    }
}
