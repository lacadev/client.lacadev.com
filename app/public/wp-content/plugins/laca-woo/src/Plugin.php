<?php

namespace LacaWoo;

use LacaWoo\Admin\AdminMenu;
use LacaWoo\Admin\Assets;
use LacaWoo\Rest\AnalyticsController;
use LacaWoo\Services\WooCommerceGuard;

final class Plugin
{
    public static function boot(): void
    {
        $guard = new WooCommerceGuard();

        if (!$guard->isActive()) {
            add_action('admin_notices', [$guard, 'renderMissingWooCommerceNotice']);
            return;
        }

        (new AdminMenu())->register();
        (new Assets())->register();
        (new AnalyticsController())->register();
    }
}
