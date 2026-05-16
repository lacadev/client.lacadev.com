<?php

namespace App\Settings\ThemeControlTabs;

class CtaTab
{
    public function render(): void
    {
        $enabled       = get_option('laca_cta_enabled', '1');
        $hideLoggedIn  = get_option('laca_cta_hide_logged_in', '0');
        $showDesktop   = get_option('laca_cta_show_desktop', '1');
        $showMobile    = get_option('laca_cta_show_mobile', '1');
        $contactUrl    = get_option('laca_cta_contact_url', home_url('/lien-he/'));
        $servicesUrl   = get_option('laca_cta_services_url', home_url('/dich-vu/'));
        $productLabel  = get_option('laca_cta_product_label', 'Mua ngay');
        $blogColor     = sanitize_hex_color(get_option('laca_cta_color_blog', '#1a6b8a')) ?: '#1a6b8a';
        $homeColor     = sanitize_hex_color(get_option('laca_cta_color_home', '#2563eb')) ?: '#2563eb';
        $pageColor     = sanitize_hex_color(get_option('laca_cta_color_page', '#2563eb')) ?: '#2563eb';
        $productColor  = sanitize_hex_color(get_option('laca_cta_color_product', '#16a34a')) ?: '#16a34a';
        $archiveColor  = sanitize_hex_color(get_option('laca_cta_color_archive', '#2563eb')) ?: '#2563eb';
        $blogLabel     = get_option('laca_cta_blog_label', 'Nhận tư vấn miễn phí →');
        $homeLabel     = get_option('laca_cta_home_label', 'Liên hệ ngay →');
        $pageLabel     = get_option('laca_cta_page_label', 'Bắt đầu dự án →');
        $archiveLabel  = get_option('laca_cta_archive_label', 'Xem tất cả dịch vụ →');
        ?>
        <div class="laca-cta-admin" data-laca-cta-admin>
            <div class="laca-cta-admin__settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Trạng thái</th>
                        <td>
                            <label><input type="checkbox" name="laca_cta_enabled" value="1" data-laca-cta-preview="enabled" <?php checked('1', $enabled); ?>>
                            Bật Sticky CTA bar</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Hiển thị theo thiết bị</th>
                        <td>
                            <label class="laca-cc-inline-check"><input type="checkbox" name="laca_cta_show_desktop" value="1" data-laca-cta-preview="desktop" <?php checked('1', $showDesktop); ?>>
                            Desktop</label>
                            <label class="laca-cc-inline-check"><input type="checkbox" name="laca_cta_show_mobile" value="1" data-laca-cta-preview="mobile" <?php checked('1', $showMobile); ?>>
                            Mobile</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Ẩn với user đã đăng nhập</th>
                        <td>
                            <label><input type="checkbox" name="laca_cta_hide_logged_in" value="1" <?php checked('1', $hideLoggedIn); ?>>
                            Ẩn CTA với người dùng đã đăng nhập</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_contact_url">URL Liên hệ</label></th>
                        <td><input type="url" id="laca_cta_contact_url" name="laca_cta_contact_url"
                                   value="<?php echo esc_attr($contactUrl); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_services_url">URL Dịch vụ</label></th>
                        <td><input type="url" id="laca_cta_services_url" name="laca_cta_services_url"
                                   value="<?php echo esc_attr($servicesUrl); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_blog_label">Label — Bài viết</label></th>
                        <td><input type="text" id="laca_cta_blog_label" name="laca_cta_blog_label"
                                   value="<?php echo esc_attr($blogLabel); ?>" class="regular-text" data-laca-cta-preview="blog">
                            <p class="description">Click vao label nay se di den URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_home_label">Label — Trang chủ</label></th>
                        <td><input type="text" id="laca_cta_home_label" name="laca_cta_home_label"
                                   value="<?php echo esc_attr($homeLabel); ?>" class="regular-text" data-laca-cta-preview="home">
                            <p class="description">Click vao label nay se di den URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_page_label">Label — Pages</label></th>
                        <td><input type="text" id="laca_cta_page_label" name="laca_cta_page_label"
                                   value="<?php echo esc_attr($pageLabel); ?>" class="regular-text" data-laca-cta-preview="page">
                            <p class="description">Click vao label nay se di den URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_product_label">Label - Product</label></th>
                        <td><input type="text" id="laca_cta_product_label" name="laca_cta_product_label"
                                   value="<?php echo esc_attr($productLabel); ?>" class="regular-text" data-laca-cta-preview="product">
                            <p class="description">Click vao label nay se di den gio hang WooCommerce; neu khong co WooCommerce thi ve URL Lien he.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="laca_cta_archive_label">Label — Archive / Search</label></th>
                        <td><input type="text" id="laca_cta_archive_label" name="laca_cta_archive_label"
                                   value="<?php echo esc_attr($archiveLabel); ?>" class="regular-text" data-laca-cta-preview="archive">
                            <p class="description">Click vao label nay se di den URL Dich vu.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Mau nen CTA</th>
                        <td>
                            <div class="laca-cta-color-grid">
                                <label>Bai viet <input type="color" name="laca_cta_color_blog" value="<?php echo esc_attr($blogColor); ?>" data-laca-cta-color="blog"></label>
                                <label>Trang chu <input type="color" name="laca_cta_color_home" value="<?php echo esc_attr($homeColor); ?>" data-laca-cta-color="home"></label>
                                <label>Pages <input type="color" name="laca_cta_color_page" value="<?php echo esc_attr($pageColor); ?>" data-laca-cta-color="page"></label>
                                <label>Product <input type="color" name="laca_cta_color_product" value="<?php echo esc_attr($productColor); ?>" data-laca-cta-color="product"></label>
                                <label>Archive / Search <input type="color" name="laca_cta_color_archive" value="<?php echo esc_attr($archiveColor); ?>" data-laca-cta-color="archive"></label>
                            </div>
                            <p class="description">Chon context trong preview de xem mau nen thay doi realtime.</p>
                        </td>
                    </tr>
                </table>
            </div>
            <aside class="laca-cta-preview" aria-label="Sticky CTA preview">
                <div class="laca-cta-preview__head">
                    <h3>Preview realtime</h3>
                    <select id="laca_cta_preview_context" data-laca-cta-preview="context">
                        <option value="blog">Bài viết</option>
                        <option value="home">Trang chủ</option>
                        <option value="page">Page</option>
                        <option value="product">Product</option>
                        <option value="archive">Archive / Search</option>
                    </select>
                </div>
                <div class="laca-cta-preview__grid">
                    <div class="laca-cta-preview__device" data-laca-preview-device="desktop">
                        <div class="laca-cta-preview__label">Desktop</div>
                        <div class="laca-cta-preview__screen laca-cta-preview__screen--desktop">
                            <div class="laca-cta-preview__page"></div>
                            <div class="laca-cta-preview__bar">
                                <a href="#" class="laca-cta-preview__button"><span data-laca-preview-label><?php echo esc_html($blogLabel); ?></span></a>
                                <button type="button" aria-label="Đóng">×</button>
                            </div>
                            <div class="laca-cta-preview__hidden">Đang ẩn trên desktop</div>
                        </div>
                    </div>
                    <div class="laca-cta-preview__device" data-laca-preview-device="mobile">
                        <div class="laca-cta-preview__label">Mobile</div>
                        <div class="laca-cta-preview__screen laca-cta-preview__screen--mobile">
                            <div class="laca-cta-preview__page"></div>
                            <div class="laca-cta-preview__bar">
                                <a href="#" class="laca-cta-preview__button"><span data-laca-preview-label><?php echo esc_html($blogLabel); ?></span></a>
                                <button type="button" aria-label="Đóng">×</button>
                            </div>
                            <div class="laca-cta-preview__hidden">Đang ẩn trên mobile</div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
        <script>
        (function() {
            var root = document.querySelector('[data-laca-cta-admin]');
            if (!root) return;

            var context = root.querySelector('[data-laca-cta-preview="context"]');
            var enabled = root.querySelector('[data-laca-cta-preview="enabled"]');
            var desktop = root.querySelector('[data-laca-cta-preview="desktop"]');
            var mobile = root.querySelector('[data-laca-cta-preview="mobile"]');
            var labels = {
                blog: root.querySelector('[data-laca-cta-preview="blog"]'),
                home: root.querySelector('[data-laca-cta-preview="home"]'),
                page: root.querySelector('[data-laca-cta-preview="page"]'),
                product: root.querySelector('[data-laca-cta-preview="product"]'),
                archive: root.querySelector('[data-laca-cta-preview="archive"]')
            };
            var colors = {
                blog: root.querySelector('[data-laca-cta-color="blog"]'),
                home: root.querySelector('[data-laca-cta-color="home"]'),
                page: root.querySelector('[data-laca-cta-color="page"]'),
                product: root.querySelector('[data-laca-cta-color="product"]'),
                archive: root.querySelector('[data-laca-cta-color="archive"]')
            };

            function updatePreview() {
                var key = context ? context.value : 'blog';
                var text = labels[key] && labels[key].value ? labels[key].value : 'Sticky CTA';
                root.querySelectorAll('[data-laca-preview-label]').forEach(function(node) {
                    node.textContent = text;
                });
                root.querySelectorAll('.laca-cta-preview__bar').forEach(function(node) {
                    node.style.backgroundColor = colors[key] && colors[key].value ? colors[key].value : '#2563eb';
                });

                root.querySelectorAll('[data-laca-preview-device]').forEach(function(device) {
                    var isDesktop = device.getAttribute('data-laca-preview-device') === 'desktop';
                    var visible = enabled.checked && (isDesktop ? desktop.checked : mobile.checked);
                    device.classList.toggle('is-hidden', !visible);
                });
            }

            root.querySelectorAll('input, select').forEach(function(input) {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });

            updatePreview();
        }());
        </script>
        <?php
    }

    public function save(array $post): void
    {
        update_option('laca_cta_enabled', isset($post['laca_cta_enabled']) ? '1' : '0');
        update_option('laca_cta_hide_logged_in', isset($post['laca_cta_hide_logged_in']) ? '1' : '0');
        update_option('laca_cta_show_desktop', isset($post['laca_cta_show_desktop']) ? '1' : '0');
        update_option('laca_cta_show_mobile', isset($post['laca_cta_show_mobile']) ? '1' : '0');
        update_option('laca_cta_contact_url', esc_url_raw($post['laca_cta_contact_url'] ?? ''));
        update_option('laca_cta_services_url', esc_url_raw($post['laca_cta_services_url'] ?? ''));
        update_option('laca_cta_blog_label', sanitize_text_field($post['laca_cta_blog_label'] ?? ''));
        update_option('laca_cta_home_label', sanitize_text_field($post['laca_cta_home_label'] ?? ''));
        update_option('laca_cta_page_label', sanitize_text_field($post['laca_cta_page_label'] ?? ''));
        update_option('laca_cta_product_label', sanitize_text_field($post['laca_cta_product_label'] ?? ''));
        update_option('laca_cta_archive_label', sanitize_text_field($post['laca_cta_archive_label'] ?? ''));
        update_option('laca_cta_color_blog', sanitize_hex_color($post['laca_cta_color_blog'] ?? '') ?: '#1a6b8a');
        update_option('laca_cta_color_home', sanitize_hex_color($post['laca_cta_color_home'] ?? '') ?: '#2563eb');
        update_option('laca_cta_color_page', sanitize_hex_color($post['laca_cta_color_page'] ?? '') ?: '#2563eb');
        update_option('laca_cta_color_product', sanitize_hex_color($post['laca_cta_color_product'] ?? '') ?: '#16a34a');
        update_option('laca_cta_color_archive', sanitize_hex_color($post['laca_cta_color_archive'] ?? '') ?: '#2563eb');
    }
}
