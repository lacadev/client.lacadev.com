<?php
/**
 * Plugin Name: Laca Woo
 * Description: WooCommerce operations dashboard for revenue, products, orders and inventory.
 * Version: 0.1.1
 * Author: LacaDev
 * Text Domain: laca-woo
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LACA_WOO_VERSION', '0.1.1');
define('LACA_WOO_FILE', __FILE__);
define('LACA_WOO_DIR', plugin_dir_path(__FILE__));
define('LACA_WOO_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'LacaWoo\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = LACA_WOO_DIR . 'src/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

add_action('plugins_loaded', ['LacaWoo\\Plugin', 'boot']);
