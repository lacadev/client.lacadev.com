<?php
/**
 * Child Theme Functions
 *
 * Child theme của lacadev-client (WPEmerge).
 * Parent theme đã bootstrap WPEmerge — child theme chỉ cần thêm overrides.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// CHILD THEME DIRECTORY CONSTANTS
// (dùng prefix CHILD_ để tránh conflict với parent constants)
// =============================================================================

define('CHILD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('CHILD_THEME_DIR', CHILD_DIR . 'theme' . DIRECTORY_SEPARATOR);
define('CHILD_APP_DIR', CHILD_DIR . 'app' . DIRECTORY_SEPARATOR);
define('CHILD_RESOURCES_DIR', CHILD_DIR . 'resources' . DIRECTORY_SEPARATOR);
define('CHILD_DIST_DIR', CHILD_DIR . 'dist' . DIRECTORY_SEPARATOR);
define('CHILD_THEME_SETUP_DIR', CHILD_THEME_DIR . 'setup' . DIRECTORY_SEPARATOR);

// =============================================================================
// CHILD THEME SETUP
// after_setup_theme chạy SAU khi parent's functions.php đã load xong
// =============================================================================

/**
 * Nạp bản dịch của child (theme/languages/{locale}.mo — câu gốc tiếng Việt).
 *
 * Không dùng load_child_theme_textdomain(): từ WP 6.7 hàm đó chỉ ghi nhận
 * đường dẫn rồi nạp just-in-time ĐÚNG 1 file — file
 * wp-content/languages/themes/laca-{locale}.mo (nếu có) sẽ thắng, và parent
 * cũng đăng ký domain 'laca' nên đường dẫn bị ghi đè. load_textdomain() nạp
 * thẳng + gộp vào domain. Gọi lại ở 'change_locale' vì switch_to_locale()
 * (email Newsletter theo ngôn ngữ khách) và Polylang gỡ textdomain khi đổi locale.
 */
function laca_child_load_textdomain($locale = ''): void
{
    $locale = $locale ?: determine_locale();
    $mofile = CHILD_THEME_DIR . 'languages' . DIRECTORY_SEPARATOR . $locale . '.mo';
    if (is_readable($mofile)) {
        load_textdomain('laca', $mofile, $locale);
    }
}
add_action('change_locale', 'laca_child_load_textdomain');

add_action('after_setup_theme', function () {
    laca_child_load_textdomain();

    // Load child assets
    require_once CHILD_THEME_SETUP_DIR . 'assets.php';

    // Menu location riêng cho header của child
    if (file_exists(CHILD_THEME_SETUP_DIR . 'menus.php')) {
        require_once CHILD_THEME_SETUP_DIR . 'menus.php';
    }

    // Language switcher cho header
    if (file_exists(CHILD_THEME_SETUP_DIR . 'language-switcher.php')) {
        require_once CHILD_THEME_SETUP_DIR . 'language-switcher.php';
    }

    // Load child theme options (Carbon Fields)
    if (file_exists(CHILD_THEME_SETUP_DIR . 'theme-options.php')) {
        require_once CHILD_THEME_SETUP_DIR . 'theme-options.php';
    }

    // Carbon Fields cho post type "page" (ẩn/hiện breadcrumb riêng từng trang)
    if (file_exists(CHILD_THEME_SETUP_DIR . 'page_meta.php')) {
        require_once CHILD_THEME_SETUP_DIR . 'page_meta.php';
    }
}, 20); // priority 20 — sau parent (10)

// =============================================================================
// CHILD HOOKS
// =============================================================================

require_once CHILD_APP_DIR . 'hooks.php';
