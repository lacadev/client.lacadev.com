<?php

/**
 * Load helpers.
 *
 * @package WPEmergeTheme
 */

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Load base helpers.
 */
require_once APP_APP_HELPERS_DIR . 'shims.php';
require_once APP_APP_HELPERS_DIR . 'admin.php';
require_once APP_APP_HELPERS_DIR . 'content.php';
require_once APP_APP_HELPERS_DIR . 'login.php';
require_once APP_APP_HELPERS_DIR . 'shortcodes.php';
require_once APP_APP_HELPERS_DIR . 'title.php';
require_once APP_APP_HELPERS_DIR . 'functions.php';
require_once APP_APP_HELPERS_DIR . 'ajax.php';
require_once APP_APP_HELPERS_DIR . 'hooks.php';
require_once APP_APP_HELPERS_DIR . 'template_tags.php';
require_once APP_APP_HELPERS_DIR . 'woocommerce.php';
require_once APP_APP_HELPERS_DIR . 'carbon_fields.php';
require_once APP_APP_SETUP_DIR . 'users/auth.php';
require_once get_template_directory() . '/setup/admin-ui.php';

/**
 * Require custom helper files here.
 */
\App\Bootstrap\FeatureRegistry::bootImmediate();
