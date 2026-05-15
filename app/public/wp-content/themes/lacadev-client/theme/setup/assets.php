<?php
/**
 * Asset helpers.
 *
 * @package WPEmergeTheme
 */

use WPEmergeTheme\Facades\Assets;
use App\Assets\AdminScriptData;
use App\Assets\AdminStyleOverrides;
use App\Assets\AssetLoadingRules;
use App\Assets\AssetPreloader;
use App\Assets\EditorStyleData;
use App\Assets\LoginAssetData;
use App\Assets\LoginInlineAssets;
use App\Assets\ProjectChartData;
use App\Assets\ReadingModeInlineAssets;
use App\Contracts\AssetHandles;

/**
 * Enhanced asset loading with performance optimizations
 */
function app_action_theme_enqueue_assets()
{
    $version = wp_get_theme()->get('Version');
    $theme_root_dir = dirname(get_stylesheet_directory());
    $theme_root_uri = dirname(get_stylesheet_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';

    /**
     * Enqueue the built-in comment-reply script for singular pages.
     */
    if (is_singular()) {
        wp_enqueue_script('comment-reply');
    }

    /**
     * Critical JS (inline or very small) - load in head for critical functionality
     */
    if (file_exists($dist_path . 'critical.js')) {
        wp_enqueue_script(AssetHandles::CRITICAL_JS, $dist_url . 'critical.js', [], $version, false);
    }

    /**
     * Vendors bundle
     */
    $vendors_deps = [];
    if (file_exists($dist_path . 'vendors.js')) {
        wp_enqueue_script(AssetHandles::VENDORS_JS, $dist_url . 'vendors.js', [], $version, true);
        $vendors_deps = [AssetHandles::VENDORS_JS];
    }

    /**
     * Main JavaScript bundle (deferred)
     */
    Assets::enqueueScript(AssetHandles::THEME_JS, $dist_url . 'theme.js', $vendors_deps, true);

    /**
     * Conditional assets based on page type
     */
    if (is_home() || is_archive() || is_search()) {
        if (file_exists($dist_path . 'archive.js')) {
            wp_enqueue_script(AssetHandles::ARCHIVE_JS, $dist_url . 'archive.js', [AssetHandles::THEME_JS], $version, true);
        }
    }

    if (is_single() && comments_open()) {
        if (file_exists($dist_path . 'comments.js')) {
            wp_enqueue_script(AssetHandles::COMMENTS_JS, $dist_url . 'comments.js', [AssetHandles::THEME_JS], $version, true);
        }
    }

    /**
     * Enqueue styles with preload optimization
     */
    Assets::enqueueStyle(AssetHandles::THEME_CSS, $dist_url . 'styles/theme.css');

    /**
     * Conditional CSS based on page type
     */
    if (is_single()) {
        if (file_exists($dist_path . 'styles/single.css')) {
            wp_enqueue_style(AssetHandles::SINGLE_CSS, $dist_url . 'styles/single.css', [AssetHandles::THEME_CSS], $version);
        }
    }

    /**
     * Enqueue theme's style.css file to allow overrides for the bundled styles.
     */
    Assets::enqueueStyle(AssetHandles::THEME_STYLES, get_template_directory_uri() . '/style.css');

    /**
     * Localize script with minimal data
     */
    wp_localize_script(AssetHandles::THEME_JS, 'themeData', [
        'ajaxurl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('theme_nonce'),
        'searchNonce'   => wp_create_nonce('theme_search_nonce'),
        'searchIndex'   => rest_url('lacadev/v1/search-index'),
        'readingModeEnabled' => get_option('laca_reading_mode_enabled', '1') === '1',
        'isHome'        => is_home(),
        'isMobile'      => wp_is_mobile(),
        'currentUrl'    => get_permalink(),
    ]);

    if (get_option('laca_reading_mode_enabled', '1') !== '1') {
        wp_add_inline_script(
            AssetHandles::THEME_JS,
            ReadingModeInlineAssets::disabledScript(),
            'after'
        );
    }
}

/**
 * Enqueue admin assets.
 *
 * @return void
 */
function app_action_admin_enqueue_assets()
{
    // dist/ nằm ở .../lacadev-client/dist/ nên cần dirname() để lên 1 level
    $template_dir = dirname(get_stylesheet_directory_uri());

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        AssetHandles::ADMIN_CSS,
        $template_dir . '/dist/styles/admin.css'
    );
    Assets::enqueueStyle(
        AssetHandles::EDITOR_CSS,
        $template_dir . '/dist/styles/editor.css'
    );

    /**
     * Enqueue vendors.js if exists (same fix as frontend)
     * CRITICAL: Load in head (false) to ensure it's available before admin.js
     */
    $admin_deps = [];
    $theme_root = dirname(get_stylesheet_directory());
    $vendors_path = $theme_root . '/dist/vendors.js';
    
    if (file_exists($vendors_path)) {
        $base_uri = get_stylesheet_directory_uri();
        $theme_uri = dirname($base_uri);
        $vendors_url = $theme_uri . '/dist/vendors.js';
        
        // Load in <head> without defer to ensure Swal is available
        wp_enqueue_script(AssetHandles::VENDORS_JS, $vendors_url, [], wp_get_theme()->get('Version'), false);
        $admin_deps = [AssetHandles::VENDORS_JS];
    }

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        AssetHandles::ADMIN_JS,
        $template_dir . '/dist/admin.js',
        $admin_deps,
        true
    );

    /**
     * Localize admin script data with nonce for AJAX requests and i18n strings
     */
    wp_localize_script(
        AssetHandles::ADMIN_JS,
        'ajaxurl_params',
        AdminScriptData::ajaxParams(admin_url('admin-ajax.php'), wp_create_nonce('update_post_thumbnail'))
    );

    /**
     * Localize i18n strings for admin JavaScript
     */
    wp_localize_script(
        AssetHandles::ADMIN_JS,
        'adminI18n',
        AdminScriptData::i18n(static fn(string $text): string => __($text, 'lacadev'))
    );

    /**
     * Localize project chart data — chỉ inject trên trang Dashboard (index.php).
     * Dữ liệu được đọc từ custom post type 'project' nếu đã đăng ký.
     */
    $current_screen = get_current_screen();
    if (ProjectChartData::shouldLocalize($current_screen)) {
        global $wpdb;

        wp_localize_script(AssetHandles::ADMIN_JS, 'lacaProjectCharts', ProjectChartData::build($wpdb));
    }

    wp_add_inline_style(AssetHandles::ADMIN_CSS, AdminStyleOverrides::css());
}

/**
 * Preload critical assets in admin_head
 */
add_action('admin_head', [AssetPreloader::class, 'printAdminPreloads'], 1);

/**
 * Enqueue login assets.
 *
 * @return void
 */
function app_action_login_enqueue_assets()
{
    $template_dir = dirname(get_stylesheet_directory_uri());

    $login_logo_url = LoginAssetData::resolveLogoUrl(carbon_get_theme_option('login_logo'));
    if (empty($login_logo_url)) {
        $login_logo_url = LoginAssetData::resolveLogoUrl(carbon_get_theme_option('logo'));
    }

    $loginLocales = LoginAssetData::buildLocales(static fn(string $key) => carbon_get_theme_option($key));

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        AssetHandles::LOGIN_JS,
        $template_dir . '/dist/login.js',
        [],
        true
    );

    wp_localize_script(
        AssetHandles::LOGIN_JS,
        'loginI18n',
        LoginAssetData::buildPayload($login_logo_url, $loginLocales, get_bloginfo('language'), home_url('/'))
    );

    // Ensure placeholders can be overridden from Carbon Fields without requiring JS rebuild.
    wp_add_inline_script(AssetHandles::LOGIN_JS, LoginInlineAssets::placeholderScript(), 'after');

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        AssetHandles::LOGIN_CSS,
        $template_dir . '/dist/styles/login.css'
    );

    // Force override login logo in case theme CSS uses !important.
    if (!empty($login_logo_url)) {
        wp_add_inline_style(AssetHandles::LOGIN_CSS, LoginInlineAssets::logoCss($login_logo_url));
    }
}

/**
 * Enqueue editor assets.
 *
 * @return void
 */
function app_action_editor_enqueue_assets()
{
    $template_dir = dirname(get_stylesheet_directory_uri());

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        AssetHandles::EDITOR_JS,
        $template_dir . '/dist/editor.js',
        [],
        true
    );

    /**
    * Enqueue styles.
    */
    Assets::enqueueStyle(
        AssetHandles::EDITOR_CSS,
        $template_dir . '/dist/styles/editor.css'
    );

    // Support for block editor styles (classic and modern)
    add_editor_style($template_dir . '/dist/styles/editor.css');

    // Inject theme colors and fonts as CSS variables for the editor.
    wp_add_inline_style(
        AssetHandles::EDITOR_CSS,
        EditorStyleData::cssVariables([
            'primary_color' => getOption('primary_color'),
            'secondary_color' => getOption('secondary_color'),
            'bg_color' => getOption('bg_color'),
            'primary_color_dark' => getOption('primary_color_dark'),
            'secondary_color_dark' => getOption('secondary_color_dark'),
            'bg_color_dark' => getOption('bg_color_dark'),
        ])
    );
}

/**
 * Add favicon proxy.
 *
 * @return void
 * @link WPEmergeTheme\Assets\Assets::addFavicon()
 */
function app_action_add_favicon()
{
    if (function_exists('has_site_icon') && has_site_icon()) {
        return;
    }

    $favicon_path = APP_RESOURCES_DIR . 'images/favicon.ico';
    if (file_exists($favicon_path) && filesize($favicon_path) > 0) {
        Assets::addFavicon();
        return;
    }

    $svg_path = APP_RESOURCES_DIR . 'images/dev/icon.svg';
    if (file_exists($svg_path)) {
        $theme_root_uri = dirname(get_stylesheet_directory_uri());
        echo '<link rel="icon" type="image/svg+xml" href="' . esc_url($theme_root_uri . '/resources/images/dev/icon.svg') . '" />' . "\n";
    }
}

/**
 * Advanced script optimization with defer/async/preload
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    return AssetLoadingRules::scriptTag($tag, $handle);
}, 10, 3);

/**
 * Inline Critical CSS + Preload assets in wp_head
 *
 * When critical.css exists (generated by `yarn critical`), its contents are
 * inlined directly into <head> so layout-essential rules (.container, body, etc.)
 * are available immediately — even though theme.css loads async.
 */
add_action('wp_head', [AssetPreloader::class, 'printFrontendPreloads'], 1);

/**
 * Enhanced resource hints for performance
 */
add_filter('wp_resource_hints', function ($hints, $relation_type) {
    return AssetLoadingRules::resourceHints(
        $hints,
        $relation_type,
        is_home() || is_front_page(),
        (string) get_permalink(get_option('page_for_posts'))
    );
}, 10, 2);

// NOTE: Các hooks được đăng ký trong app/hooks.php — không thêm lại ở đây để tránh duplicate.
