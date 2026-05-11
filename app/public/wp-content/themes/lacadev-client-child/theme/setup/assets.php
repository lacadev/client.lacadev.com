<?php
/**
 * Child Theme Asset Enqueue
 *
 * Parent theme đã enqueue tất cả assets chính.
 * File này chỉ thêm child-specific overrides.
 *
 * QUAN TRỌNG: Dùng App\Contracts\AssetHandles constants thay magic strings.
 * Nếu parent đổi tên handle → chỉ cần cập nhật constant value, không sửa file này.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

use App\Contracts\AssetHandles;

/**
 * Enqueue child theme frontend assets.
 * Chạy SAU parent's app_action_theme_enqueue_assets() nhờ priority 20.
 */
function child_enqueue_frontend_assets(): void
{
    $v   = wp_get_theme()->get('Version');
    $uri = get_stylesheet_directory_uri();
    $dir = get_stylesheet_directory();

    // ------------------------------------------------------------------
    // CSS override từ resources/ (dùng khi không có build step)
    // ------------------------------------------------------------------
    $child_css_file = $dir . '/resources/styles/child.css';
    if (file_exists($child_css_file)) {
        wp_enqueue_style(
            'child-theme-css',
            $uri . '/resources/styles/child.css',
            [AssetHandles::THEME_CSS],
            $v
        );
    }

    // ------------------------------------------------------------------
    // CSS từ dist/ (dùng khi có Webpack/PostCSS build)
    // ------------------------------------------------------------------
    $dist_css = $dir . '/dist/styles/child.css';
    if (file_exists($dist_css)) {
        wp_enqueue_style(
            'child-dist-css',
            $uri . '/dist/styles/child.css',
            [AssetHandles::THEME_CSS],
            filemtime($dist_css)
        );
    }

    // ------------------------------------------------------------------
    // JS từ dist/ (nếu có custom JS)
    // ------------------------------------------------------------------
    $dist_js = $dir . '/dist/child.js';
    if (file_exists($dist_js)) {
        wp_enqueue_script(
            'child-theme-js',
            $uri . '/dist/child.js',
            [AssetHandles::THEME_JS],
            filemtime($dist_js),
            true
        );
    }

    // ------------------------------------------------------------------
    // Stats Counter Block animation script
    // ------------------------------------------------------------------
    $sc_js = $dir . '/block-gutenberg/block-stats-counter/stats-counter.js';
    if (file_exists($sc_js)) {
        wp_enqueue_script(
            'block-stats-counter-js',
            $uri . '/block-gutenberg/block-stats-counter/stats-counter.js',
            [],
            filemtime($sc_js),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'child_enqueue_frontend_assets', 20);

/**
 * Enqueue child theme admin assets.
 */
function child_enqueue_admin_assets(): void
{
    $uri = get_stylesheet_directory_uri();
    $dir = get_stylesheet_directory();

    $admin_css = $dir . '/dist/styles/admin-child.css';
    if (file_exists($admin_css)) {
        wp_enqueue_style(
            'child-admin-css',
            $uri . '/dist/styles/admin-child.css',
            [AssetHandles::ADMIN_CSS],
            filemtime($admin_css)
        );
    }
}
add_action('admin_enqueue_scripts', 'child_enqueue_admin_assets', 20);
