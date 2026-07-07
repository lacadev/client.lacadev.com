<?php

namespace LacaWoo\Admin;

final class AdminMenu
{
    public const SLUG = 'laca-woo';
    public const CAPABILITY = 'manage_woocommerce';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('Laca Woo', 'laca-woo'),
            __('Laca Woo', 'laca-woo'),
            self::CAPABILITY,
            self::SLUG,
            [new AdminPage(), 'render'],
            'dashicons-cart',
            56
        );
    }
}
