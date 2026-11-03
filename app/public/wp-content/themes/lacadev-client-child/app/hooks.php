<?php
/**
 * Child Theme Hooks
 *
 * Đăng ký các actions và filters riêng của child theme.
 * Parent theme hooks vẫn chạy bình thường — chỉ thêm/ghi đè ở đây.
 *
 * @package LacaDevClientChild
 */

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// CHILD HOOKS — thêm hooks của bạn bên dưới
// =============================================================================

// ── Helpers ─────────────────────────────────────────────────────────────────
require_once CHILD_APP_DIR . 'helpers/layout-mode.php';
require_once CHILD_APP_DIR . 'helpers/block-image-render.php';
require_once CHILD_APP_DIR . 'helpers/google-fonts-vi.php';
require_once CHILD_APP_DIR . 'helpers/taxonomy-helpers.php';
require_once CHILD_APP_DIR . 'helpers/ajax-pagination-markup.php';

// ── Gallery/PdnTv/Project Archive AJAX Handlers ─────────────────────────────
if (file_exists(CHILD_APP_DIR . 'src/Ajax/GalleryAjaxHandler.php')) {
    require_once CHILD_APP_DIR . 'src/Ajax/GalleryAjaxHandler.php';
}
if (file_exists(CHILD_APP_DIR . 'src/Ajax/PdnTvAjaxHandler.php')) {
    require_once CHILD_APP_DIR . 'src/Ajax/PdnTvAjaxHandler.php';
}
if (file_exists(CHILD_APP_DIR . 'src/Ajax/ProjectAjaxHandler.php')) {
    require_once CHILD_APP_DIR . 'src/Ajax/ProjectAjaxHandler.php';
}

// ── Newsletter (tuỳ chọn — bật/tắt trong Theme Options) ─────────────────────
if (file_exists(CHILD_APP_DIR . 'src/Newsletter/NewsletterTexts.php')) {
    require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterTexts.php';
}

add_action('init', function () {
    $newsletter_enabled = function_exists('carbon_get_theme_option') && carbon_get_theme_option('newsletter_enable');
    if (!$newsletter_enabled) {
        return;
    }

    if (file_exists(CHILD_APP_DIR . 'src/Newsletter/NewsletterSubscribers.php')) {
        require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterSubscribers.php';
    }
    if (file_exists(CHILD_APP_DIR . 'src/Newsletter/NewsletterSync.php')) {
        require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterSync.php';
    }
    if (file_exists(CHILD_APP_DIR . 'src/Newsletter/NewsletterMailer.php')) {
        require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterMailer.php';
    }
    if (file_exists(CHILD_APP_DIR . 'src/Newsletter/NewsletterEndpoints.php')) {
        require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterEndpoints.php';
    }
    if (file_exists(CHILD_APP_DIR . 'src/Ajax/NewsletterAjaxHandler.php')) {
        require_once CHILD_APP_DIR . 'src/Ajax/NewsletterAjaxHandler.php';
    }
    if (is_admin() && file_exists(CHILD_APP_DIR . 'src/Newsletter/NewsletterAdminPage.php')) {
        require_once CHILD_APP_DIR . 'src/Newsletter/NewsletterAdminPage.php';
    }
}, 1);

// ── PageSpeed Insights: Tối ưu hiệu năng tải trang ──────────────────────────
// 1. Inline CSS của block custom (block-block-*, mỗi file 1-5KB) thay vì <link>
$laca_inline_block_styles = function () {
    $wp_styles = wp_styles();
    foreach ($wp_styles->registered as $handle => $style) {
        if (strpos($handle, 'block-block-') !== 0 || !is_string($style->src) || $wp_styles->get_data($handle, 'path')) {
            continue;
        }
        $path = str_replace(content_url(), WP_CONTENT_DIR, strtok($style->src, '?'));
        if (is_readable($path)) {
            $wp_styles->add_data($handle, 'path', $path);
        }
    }
};
add_action('wp_enqueue_scripts', $laca_inline_block_styles, 999);
add_action('wp_footer', $laca_inline_block_styles, 0);

// 2. Block Accordion của core render panel KHÔNG có hidden — ẩn sẵn panel đóng
add_action('wp_enqueue_scripts', function () {
    wp_add_inline_style(
        'wp-block-accordion-panel',
        '.wp-block-accordion-heading:has(.wp-block-accordion-heading__toggle[aria-expanded="false"])+.wp-block-accordion-panel{display:none}'
    );
}, 20);

// 3. Nâng giới hạn CSS inline mặc định
add_filter('styles_inline_size_limit', function () {
    return 100000;
});

// 4. Critical CSS riêng theo loại trang (home, page-hero, page)
add_filter('lacadev_critical_css_path', function ($path, $dist_path) {
    if (is_front_page()) {
        $type = 'home';
    } elseif (is_singular() && function_exists('has_block') && has_block('lacadev/top-hero-block')) {
        $type = 'page-hero';
    } else {
        $type = 'page';
    }

    $typed_path = $dist_path . 'styles/critical-' . $type . '.css';
    return file_exists($typed_path) ? $typed_path : $path;
}, 10, 2);

// 5. Chuyển jQuery xuống footer và loại bỏ jquery-migrate ở frontend
add_action('wp_default_scripts', function ($scripts) {
    if (!is_admin() && !is_customize_preview() && isset($scripts->registered['jquery'])) {
        $jquery = $scripts->registered['jquery'];
        if ($jquery->deps) {
            $jquery->deps = array_diff($jquery->deps, ['jquery-migrate']);
        }
    }
});

add_action('wp_enqueue_scripts', function () {
    if (!is_admin() && !is_customize_preview()) {
        wp_scripts()->add_data('jquery', 'group', 1);
        wp_scripts()->add_data('jquery-core', 'group', 1);
        wp_scripts()->add_data('jquery-migrate', 'group', 1);
        $is_wc_flow = function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page());
        if (!$is_wc_flow) {
            wp_script_add_data('jquery-core', 'strategy', 'defer');
        }
    }
}, 1);

// 6. Dequeue script không cần thiết
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || is_customize_preview()) {
        return;
    }

    // comment-reply: chỉ nạp khi trang mở bình luận và có thread comments
    if (!is_singular() || !comments_open() || !get_option('thread_comments')) {
        wp_dequeue_script('comment-reply');
    }
}, 99);

// 7. Tắt Emoji WordPress ở frontend
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
});
