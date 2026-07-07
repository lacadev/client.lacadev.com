<?php

namespace LacaWoo\Admin;

final class Assets
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . AdminMenu::SLUG) {
            return;
        }

        wp_enqueue_style(
            'laca-woo-admin',
            LACA_WOO_URL . 'assets/css/admin-dashboard.css',
            [],
            LACA_WOO_VERSION
        );

        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js',
            [],
            '4.5.1',
            true
        );

        wp_enqueue_script(
            'laca-woo-admin',
            LACA_WOO_URL . 'assets/js/admin-dashboard.js',
            ['chart-js', 'wp-api-fetch'],
            LACA_WOO_VERSION,
            true
        );

        wp_localize_script('laca-woo-admin', 'lacaWooAdmin', [
            'restUrl' => esc_url_raw(rest_url('laca-woo/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'VND',
            'currencySymbol' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '',
            'locale' => get_locale(),
            'i18n' => [
                'loading' => __('Đang tải dữ liệu...', 'laca-woo'),
                'empty' => __('Chưa có dữ liệu.', 'laca-woo'),
                'error' => __('Không tải được dữ liệu WooCommerce.', 'laca-woo'),
            ],
        ]);
    }
}
