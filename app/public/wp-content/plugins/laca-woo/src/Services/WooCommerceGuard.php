<?php

namespace LacaWoo\Services;

final class WooCommerceGuard
{
    public function isActive(): bool
    {
        return class_exists('WooCommerce') && function_exists('wc_get_orders') && function_exists('wc_get_products');
    }

    public function renderMissingWooCommerceNotice(): void
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('Laca Woo cần WooCommerce đang hoạt động để hiển thị báo cáo.', 'laca-woo');
        echo '</p></div>';
    }
}
