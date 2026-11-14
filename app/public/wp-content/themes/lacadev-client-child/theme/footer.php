<?php

/**
 * Theme footer partial.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */

/**
 * Icon SVG inline dùng riêng cho footer.php (mạng xã hội) — không phụ thuộc
 * font/icon-library ngoài.
 */
if (!function_exists('laca_footer_social_icon')) {
    function laca_footer_social_icon(string $name): string
    {
        $icons = [
            'facebook' => '<path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"></path>',
            'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="2"></rect><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"></circle><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"></circle>',
            'tiktok' => '<path d="M16.6 5.82A4.28 4.28 0 0 1 15.83 3h-3.3v13.6a2.6 2.6 0 1 1-1.83-2.48V10.7a5.8 5.8 0 1 0 5.13 5.76V9.4a7.5 7.5 0 0 0 4.17 1.27V7.4a4.27 4.27 0 0 1-3.4-1.58z"></path>',
            'youtube' => '<path d="M22 12s0-3.2-.4-4.7a2.5 2.5 0 0 0-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.5a2.5 2.5 0 0 0-1.8 1.8C2 8.8 2 12 2 12s0 3.2.4 4.7a2.5 2.5 0 0 0 1.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.5a2.5 2.5 0 0 0 1.8-1.8c.4-1.5.4-4.7.4-4.7z" fill="none" stroke="currentColor" stroke-width="2"></path><polygon points="10 15 15 12 10 9 10 15"></polygon>',
            'linkedin' => '<path d="M4.98 3.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM3 9h4v12H3zM9 9h3.8v1.64h.05c.53-1 1.83-2.06 3.77-2.06C20.9 8.58 21 11.36 21 14.2V21h-4v-6.14c0-1.46-.03-3.34-2.04-3.34-2.04 0-2.35 1.6-2.35 3.24V21H9z"></path>',
            'zalo' => '<rect x="2" y="2" width="20" height="20" rx="4" ry="4" fill="none" stroke="currentColor" stroke-width="2"></rect><path d="M6.5 16.5h5M8 16.5V9l-3 4M13 9h4M15 9v7.5M13 13h3.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"></path>',
        ];

        if (!isset($icons[$name])) {
            return '';
        }

        return '<svg class="footer__social-icon" width="1em" height="1em" style="width:1em;height:1em;" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">' . $icons[$name] . '</svg>';
    }
}

$footer_company_name = function_exists('getOption') ? getOption('footer_company_name') : '';
$footer_business_reg = function_exists('getOption') ? getOption('footer_business_reg') : '';
$footer_address = function_exists('getOption') ? getOption('address') : '';
$footer_email = function_exists('getOption') ? getOption('email') : '';

$footer_links_col1 = function_exists('getOption') ? getOption('footer_links_col1') : [];
$footer_links_col1 = is_array($footer_links_col1) ? $footer_links_col1 : [];
$footer_links_col2 = function_exists('getOption') ? getOption('footer_links_col2') : [];
$footer_links_col2 = is_array($footer_links_col2) ? $footer_links_col2 : [];

$footer_badges = function_exists('getOption') ? getOption('footer_badges') : [];
if (empty($footer_badges)) {
    $fallback_badges = function_exists('laca_theme_option') ? laca_theme_option('footer_badges') : (function_exists('carbon_get_theme_option') ? carbon_get_theme_option('footer_badges') : []);
    if (empty($fallback_badges)) {
        $fallback_badges = function_exists('laca_theme_option') ? (laca_theme_option('footer_badgesen') ?: laca_theme_option('footer_badgesvi')) : (function_exists('carbon_get_theme_option') ? (carbon_get_theme_option('footer_badgesen') ?: carbon_get_theme_option('footer_badgesvi')) : []);
    }
    if (!empty($fallback_badges)) {
        $footer_badges = $fallback_badges;
    }
}
$footer_badges = is_array($footer_badges) ? $footer_badges : [];

// Lấy DMCA badge
$footer_dmca_badge = function_exists('laca_theme_option') ? laca_theme_option('footer_dmca_badge') : (function_exists('carbon_get_theme_option') ? carbon_get_theme_option('footer_dmca_badge') : '');
if (empty($footer_dmca_badge) && function_exists('getOption')) {
    $footer_dmca_badge = getOption('footer_dmca_badge');
}
if (empty($footer_dmca_badge)) {
    $footer_dmca_badge = function_exists('laca_theme_option') ? (laca_theme_option('footer_dmca_badgeen') ?: laca_theme_option('footer_dmca_badgevi')) : (function_exists('carbon_get_theme_option') ? (carbon_get_theme_option('footer_dmca_badgeen') ?: carbon_get_theme_option('footer_dmca_badgevi')) : '');
}

$footer_socials = [
    'facebook' => function_exists('getOption') ? getOption('facebook') : '',
    'instagram' => function_exists('getOption') ? getOption('instagram') : '',
    'tiktok' => function_exists('getOption') ? getOption('tiktok') : '',
    'youtube' => function_exists('getOption') ? getOption('youtube') : '',
    'linkedin' => function_exists('getOption') ? getOption('linkedin') : '',
    'zalo' => function_exists('getOption') ? getOption('zalo') : '',
];

$container_class = function_exists('laca_block_layout_class') ? laca_block_layout_class('container') : 'container-fluid';

// Kiểm tra form liên hệ footer
$footer_contact_image_id = function_exists('laca_theme_option') ? (int) laca_theme_option('footer_contact_image') : (function_exists('carbon_get_theme_option') ? (int) carbon_get_theme_option('footer_contact_image') : 0);
$footer_contact_budget_raw = function_exists('laca_theme_option') ? trim((string) laca_theme_option('footer_contact_budget_options')) : (function_exists('carbon_get_theme_option') ? trim((string) carbon_get_theme_option('footer_contact_budget_options')) : '');
?>

<?php if ($footer_contact_image_id || $footer_contact_budget_raw !== ''): ?>
    <?php
    $footer_contact_heading = __('NHẬN TƯ VẤN NGAY', 'laca');
    $footer_contact_button_text = __('GỬI YÊU CẦU', 'laca');
    $footer_contact_budget_options = $footer_contact_budget_raw !== ''
        ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $footer_contact_budget_raw))))
        : [
            __('Dưới 1 tỷ', 'laca'),
            __('1 - 3 tỷ', 'laca'),
            __('3 - 5 tỷ', 'laca'),
            __('5 - 10 tỷ', 'laca'),
            __('Trên 10 tỷ', 'laca'),
        ];
    $footer_contact_image_url = $footer_contact_image_id ? wp_get_attachment_image_url($footer_contact_image_id, 'large') : '';
    $footer_contact_image_alt = $footer_contact_image_id ? get_post_meta($footer_contact_image_id, '_wp_attachment_image_alt', true) : '';
    ?>
    <section class="footer-contact-form">
        <div class="container">
            <div class="bcf__inner">
                <div class="bcf__left" data-aos="fade-right">
                    <?php if ($footer_contact_heading): ?>
                        <h2 class="bcf__heading"><?php echo esc_html($footer_contact_heading); ?></h2>
                    <?php endif; ?>

                    <form class="bcf__form" method="POST" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" novalidate data-bcf-form
                        data-msg-success="<?php echo esc_attr__('Gửi thành công!', 'laca'); ?>"
                        data-msg-error="<?php echo esc_attr__('Có lỗi, vui lòng thử lại.', 'laca'); ?>"
                        data-msg-network="<?php echo esc_attr__('Lỗi kết nối.', 'laca'); ?>">
                        <?php wp_nonce_field('laca_footer_contact_nonce', 'nonce'); ?>
                        <input type="hidden" name="action" value="laca_footer_contact_submit">

                        <div class="bcf__field">
                            <input type="text" name="tf_address" class="bcf__input"
                                placeholder="<?php esc_attr_e('Địa chỉ xây dựng', 'laca'); ?>" required>
                        </div>

                        <div class="bcf__field">
                            <input type="tel" name="tf_phone" class="bcf__input"
                                placeholder="<?php esc_attr_e('Số điện thoại của bạn', 'laca'); ?>" required>
                        </div>

                        <div class="bcf__field">
                            <select name="tf_budget" class="bcf__select" required>
                                <option value="" disabled selected><?php esc_html_e('Mức đầu tư dự kiến', 'laca'); ?></option>
                                <?php foreach ($footer_contact_budget_options as $option): ?>
                                    <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="bcf__field">
                            <textarea name="tf_note" class="bcf__textarea" rows="4"
                                placeholder="<?php esc_attr_e('Ghi chú thêm về yêu cầu của bạn...', 'laca'); ?>"></textarea>
                        </div>

                        <div class="bcf__submit-wrap">
                            <button type="submit" class="bcf__btn">
                                <span class="bcf__btn-text"><?php echo esc_html($footer_contact_button_text); ?></span>
                                <span class="bcf__btn-loader" hidden>
                                    <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                        <circle class="path" cx="12" cy="12" r="10" fill="none" stroke-width="3" />
                                    </svg>
                                </span>
                            </button>
                        </div>

                        <div class="bcf__msg" role="status" aria-live="polite" hidden></div>
                    </form>
                </div>

                <?php if ($footer_contact_image_url): ?>
                    <div class="bcf__right" data-aos="fade-left">
                        <img src="<?php echo esc_url($footer_contact_image_url); ?>"
                            alt="<?php echo esc_attr($footer_contact_image_alt ?: __('Form liên hệ', 'laca')); ?>"
                            class="bcf__image" loading="lazy">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php get_template_part('template-parts/newsletter-form'); ?>

<footer class="footer" role="contentinfo" data-aos="fade-up">
    <div class="<?php echo esc_attr($container_class); ?>">
        <hr class="footer__rule" />

        <div class="footer__grid">
            <div class="footer__col footer__col--company">
                <ul class="footer__list">
                    <?php if ($footer_company_name): ?>
                        <li><?php echo esc_html($footer_company_name); ?></li>
                    <?php endif; ?>
                    <?php if ($footer_business_reg): ?>
                        <li><?php echo esc_html($footer_business_reg); ?></li>
                    <?php endif; ?>
                    <?php if ($footer_address): ?>
                        <li><?php echo esc_html($footer_address); ?></li>
                    <?php endif; ?>
                    <?php if ($footer_email): ?>
                        <li><a href="mailto:<?php echo esc_attr($footer_email); ?>"><?php echo esc_html($footer_email); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if (!empty($footer_links_col1)): ?>
                <div class="footer__col">
                    <ul class="footer__list">
                        <?php foreach ($footer_links_col1 as $item):
                            if (empty($item['name'])) {
                                continue;
                            }
                            ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (function_exists('getOption') && ($serviceItems = getOption('service_footer_items'))): ?>
                <div class="footer__col">
                    <?php if ($st = getOption('service_footer_title')): ?><h3 class="footer__title"><?php echo esc_html($st); ?></h3><?php endif; ?>
                    <ul class="footer__list">
                        <?php foreach ($serviceItems as $item): ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name'] ?? ''); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($footer_links_col2)): ?>
                <div class="footer__col">
                    <ul class="footer__list">
                        <?php foreach ($footer_links_col2 as $item):
                            if (empty($item['name'])) {
                                continue;
                            }
                            ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (function_exists('getOption') && ($policyItems = getOption('policy_footer_items'))): ?>
                <div class="footer__col">
                    <?php if ($pt = getOption('policy_footer_title')): ?><h3 class="footer__title"><?php echo esc_html($pt); ?></h3><?php endif; ?>
                    <ul class="footer__list">
                        <?php foreach ($policyItems as $item): ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name'] ?? ''); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="footer__col footer__col--end">
                <?php if (!empty($footer_badges)): ?>
                    <ul class="footer__list">
                        <?php foreach ($footer_badges as $item):
                            if (empty($item['name'])) {
                                continue;
                            }
                            ?>
                            <li><a href="<?php echo esc_url($item['url'] ?? '#'); ?>"><?php echo esc_html($item['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($footer_dmca_badge)):
                    $optimized_dmca = $footer_dmca_badge;
                    if (strpos($optimized_dmca, '<img') !== false) {
                        if (strpos($optimized_dmca, 'width=') === false) {
                            $optimized_dmca = preg_replace('/<img\s+/i', '<img width="121" height="24" loading="lazy" decoding="async" ', $optimized_dmca);
                        }
                    }
                    if (strpos($optimized_dmca, '<script') !== false && strpos($optimized_dmca, 'defer') === false) {
                        $optimized_dmca = str_replace('<script ', '<script defer ', $optimized_dmca);
                    }
                    ?>
                    <div class="footer__dmca">
                        <?php echo $optimized_dmca; ?>
                    </div>
                <?php endif; ?>

                <?php
                $has_socials = array_filter($footer_socials);
                if (!empty($has_socials)):
                    ?>
                    <div class="footer__socials">
                        <?php foreach ($footer_socials as $network => $url):
                            if (!$url) {
                                continue;
                            }
                            ?>
                            <a href="<?php echo esc_url($url); ?>" class="footer__social-link" target="_blank"
                                rel="noopener noreferrer" aria-label="<?php echo esc_attr(ucfirst($network)); ?>">
                                <?php echo laca_footer_social_icon($network); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>
<!-- footer end -->

<?php
$floating_phone = function_exists('getOption') ? getOption('phone_number') : '';
$floating_zalo = function_exists('getOption') ? getOption('zalo') : '';
?>
<!-- Nút liên hệ nổi -->
<div id="floating-contact-group" class="floating-contact-group is-hidden">
    <a id="floating-back-to-top" class="floating-contact-btn" href="#"
        aria-label="<?php esc_attr_e('Lên đầu trang', 'laca'); ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"></polyline>
        </svg>
    </a>
    <?php if ($floating_phone): ?>
        <a id="floating-phone" class="floating-contact-btn"
            href="tel:<?php echo esc_attr(str_replace(['.', ',', ' '], '', $floating_phone)); ?>"
            aria-label="<?php esc_attr_e('Gọi điện', 'laca'); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z">
                </path>
            </svg>
        </a>
    <?php endif; ?>
    <?php if ($floating_zalo): ?>
        <a id="floating-zalo" class="floating-contact-btn" target="_blank" rel="noopener noreferrer"
            href="<?php echo esc_url($floating_zalo); ?>" aria-label="Zalo">
            <?php echo laca_footer_social_icon('zalo'); ?>
        </a>
    <?php endif; ?>
</div>
<button type="button" id="floating-contact-toggle" class="floating-contact-toggle"
    title="<?php esc_attr_e('Liên hệ', 'laca'); ?>" aria-label="<?php esc_attr_e('Liên hệ', 'laca'); ?>"
    aria-expanded="false">
    <svg class="floating-contact-toggle__icon-open" width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path
            d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z">
        </path>
    </svg>
    <svg class="floating-contact-toggle__icon-close" width="24" height="24" viewBox="0 0 24 24" fill="none"
        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>
</button>

</div>
<!-- wrapper end -->

<?php wp_footer(); ?>
</body>

</html>
