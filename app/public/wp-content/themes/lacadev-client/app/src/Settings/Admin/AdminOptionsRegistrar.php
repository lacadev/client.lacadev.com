<?php

namespace App\Settings\Admin;

use Carbon_Fields\Container;
use Carbon_Fields\Container\Theme_Options_Container;
use Carbon_Fields\Field;

final class AdminOptionsRegistrar
{
    public static function register(): void
    {
        add_action('carbon_fields_register_fields', static function () {
            $root = self::registerRootOptions();

            self::registerToolsOptions($root);
            self::registerBlockSyncOptions($root);
            self::registerTrackerOptions($root);
            self::registerRecaptchaOptions($root);
            self::registerProjectNotificationsOptions($root);
            self::registerLoginSocialOptions($root);
            self::registerDashboardWidgetOptions($root);
            self::registerHelpContentOptions($root);
        });
    }

    private static function registerRootOptions(): Theme_Options_Container
    {
        return Container::make('theme_options', __('Laca Admin', 'laca'))
            ->set_page_file(__('laca-admin', 'laca'))
            ->set_page_menu_position(3)
            ->add_tab(__('ADMIN', 'laca'), self::adminTabFields())
            ->add_tab(__('SMTP', 'laca'), self::smtpTabFields())
            ->add_tab(__('LOGIN', 'laca'), self::loginTabFields());
    }

    private static function registerToolsOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('Tools', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-tools', 'laca'))
            ->add_tab(__('Optimization', 'laca'), self::optimizationTabFields())
            ->add_tab(__('Security', 'laca'), self::securityTabFields());
    }

    private static function registerBlockSyncOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('LacaDev', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-block-sync', 'laca'))
            ->add_fields([
                Field::make('separator', 'sep_block_sync_heading', __('Block Sync — Nhận blocks từ lacadev.com', 'laca')),
                Field::make('html', 'block_sync_api_key_display', __('API Key', 'laca'))
                    ->set_html(static function () {
                        return AdminOptionHtml::blockSyncApiKey(\App\Settings\BlockSyncReceiver::ensureApiKey());
                    }),
                Field::make('html', 'block_sync_endpoint_info', '')
                    ->set_html(static function () {
                        return AdminOptionHtml::blockSyncEndpoint(rest_url('lacadev/v1/sync-block'));
                    }),
            ]);
    }

    private static function registerTrackerOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('Tracker', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-tracker', 'laca'))
            ->add_fields([
                Field::make('html', 'tracker_info', '')
                    ->set_html(AdminOptionHtml::trackerInfo()),
                Field::make('html', 'tracker_status_html', '')
                    ->set_html(static function () {
                        return AdminOptionHtml::trackerStatus(\App\Settings\LacaDevTrackerClient::isConfigured());
                    }),
                Field::make('separator', 'sep_tracker', __('Kết nối với lacadev.com', 'laca')),
                Field::make('text', 'laca_tracker_endpoint', __('Tracker Endpoint URL', 'laca'))
                    ->set_width(60)
                    ->set_attribute('placeholder', 'https://lacadev.com/wp-json/laca/v1/tracker/log')
                    ->set_help_text('REST URL của lacadev CMS. Copy từ trang Project → Tracker trên lacadev.com.'),
                Field::make('text', 'laca_tracker_secret_key', __('Secret Key', 'laca'))
                    ->set_width(40)
                    ->set_attribute('placeholder', 'sk_xxxxxxxx')
                    ->set_attribute('type', 'password')
                    ->set_help_text('Secret key riêng của project. Không chia sẻ key này.'),
                Field::make('html', 'tracker_save_note', '')
                    ->set_html(AdminOptionHtml::trackerSaveNote()),
            ]);
    }

    private static function registerRecaptchaOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('Google reCAPTCHA', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-recaptcha', 'laca'))
            ->add_fields([
                Field::make('html', 'recaptcha_info', '')
                    ->set_html('<div class="carbon-field-description">Bảo vệ website khỏi spam/bot bằng Google reCAPTCHA v3. <a href="https://www.google.com/recaptcha/admin/create" target="_blank">Đăng ký Key tại đây</a>.</div>'),
                Field::make('text', 'recaptcha_site_key', __('Site Key', 'laca'))
                    ->set_width(50)
                    ->set_attribute('placeholder', '6Le...'),
                Field::make('text', 'recaptcha_secret_key', __('Secret Key', 'laca'))
                    ->set_width(50)
                    ->set_attribute('type', 'password')
                    ->set_attribute('placeholder', '6Le...'),
                Field::make('separator', 'recaptcha_separator', __('Cấu hình hiển thị', 'laca')),
                Field::make('checkbox', 'enable_recaptcha_login', __('Kích hoạt cho Đăng nhập', 'laca'))
                    ->set_width(25)
                    ->set_default_value(true),
                Field::make('checkbox', 'enable_recaptcha_register', __('Kích hoạt cho Đăng ký', 'laca'))
                    ->set_width(25)
                    ->set_default_value(true),
                Field::make('checkbox', 'enable_recaptcha_comment', __('Kích hoạt cho Bình luận', 'laca'))
                    ->set_width(25)
                    ->set_default_value(true),
                Field::make('text', 'recaptcha_score', __('Điểm tối thiểu (0.0 - 1.0)', 'laca'))
                    ->set_width(25)
                    ->set_default_value('0.5')
                    ->set_attribute('type', 'number')
                    ->set_attribute('step', '0.1')
                    ->set_attribute('min', '0.0')
                    ->set_attribute('max', '1.0')
                    ->set_help_text('Bot thường < 0.5. Người dùng thật thường > 0.5.'),
            ]);
    }

    private static function registerProjectNotificationsOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('LacaDev PM & Bots', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-project-notifications', 'laca'))
            ->add_tab(__('Zalo OA (Project Manager)', 'laca'), [
                Field::make('html', 'zalo_oa_info')
                    ->set_html('<div class="carbon-field-description">Cấu hình Zalo Official Account (OA) để nhận cảnh báo về dự án (hết hạn hosting, domain, lỗi bảo mật).</div>'),
                Field::make('checkbox', 'enable_zalo_notify', __('Bật thông báo Zalo', 'laca')),
                Field::make('text', 'zalo_oa_access_token', __('Access Token', 'laca'))
                    ->set_width(50),
                Field::make('text', 'zalo_oa_refresh_token', __('Refresh Token', 'laca'))
                    ->set_width(50),
                Field::make('text', 'zalo_default_receiver', __('Zalo User ID nhận mặc định', 'laca'))
                    ->set_help_text('Nhập danh sách Zalo User ID (cách nhau bằng dấu phẩy) của Admin để nhận các cảnh báo quan trọng.'),
            ])
            ->add_tab(__('Email (Project Manager)', 'laca'), [
                Field::make('checkbox', 'enable_email_notify', __('Bật thông báo qua Email', 'laca')),
                Field::make('text', 'project_admin_email', __('Email nhận thông báo', 'laca'))
                    ->set_default_value(get_option('admin_email'))
                    ->set_help_text('Bạn có thể nhập nhiều email cách nhau bởi dấu phẩy (,).'),
            ]);
    }

    private static function registerLoginSocialOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('Login Socials', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-login-socials', 'laca'))
            ->add_tab(__('Google', 'laca'), [
                Field::make('checkbox', 'enable_login_google', __('Bật Login Google', 'laca')),
                Field::make('text', 'google_client_id', __('Client ID', 'laca'))
                    ->set_width(50),
                Field::make('text', 'google_client_secret', __('Client Secret', 'laca'))
                    ->set_width(50),
                Field::make('text', 'google_redirect_uri', __('Redirect URI', 'laca'))
                    ->set_attribute('readOnly', true)
                    ->set_default_value(home_url('/wp-admin/admin-ajax.php?action=social_login_callback&driver=google')),
            ]);
    }

    private static function registerDashboardWidgetOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('Dashboard Widgets', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-management-dashboard-widgets', 'laca'))
            ->add_fields([
                Field::make('html', 'dashboard_widgets_desc')
                    ->set_html('<div class="carbon-field-description">Chọn widget custom nào được hiển thị ngoài màn hình Dashboard. Widget không được chọn sẽ không đăng ký ra Dashboard.</div>'),
                Field::make('multiselect', 'dashboard_widgets_enabled', __('Widget hiển thị ngoài Dashboard', 'laca'))
                    ->set_options(function () {
                        return function_exists('lacadev_dashboard_widget_definitions')
                            ? lacadev_dashboard_widget_definitions()
                            : [];
                    })
                    ->set_default_value(function_exists('lacadev_dashboard_widget_definitions') ? array_keys(lacadev_dashboard_widget_definitions()) : [])
                    ->set_help_text(__('Chỉ những widget được chọn mới hiển thị ở Dashboard chính.', 'laca')),
                Field::make('separator', 'content_report_separator', __('Widget báo cáo nội dung', 'laca')),
                Field::make('multiselect', 'dashboard_widget_post_types', __('Các Post Type hiển thị', 'laca'))
                    ->set_options(function () {
                        $types = get_post_types(['public' => true, 'show_in_menu' => true], 'objects');
                        $options = [];
                        foreach ($types as $pt) {
                            if (in_array($pt->name, ['attachment', 'wp_block', 'wp_template', 'wp_template_part'], true)) {
                                continue;
                            }
                            $options[$pt->name] = $pt->label;
                        }
                        return $options;
                    })
                    ->set_help_text(__('Để trống để tự động hiển thị tất cả các loại nội dung quan trọng (Posts, Services, Projects, Properties...).', 'laca'))
                    ->set_default_value(['post']),
                Field::make('text', 'dashboard_widget_limit', __('Số lượng bài hiển thị', 'laca'))
                    ->set_attribute('type', 'number')
                    ->set_default_value('5')
                    ->set_width(50),
                Field::make('separator', 'performance_budget_separator', __('Widget Performance Budget', 'laca')),
                Field::make('html', 'performance_budget_desc')
                    ->set_html('<div class="carbon-field-description">Performance Budget dùng để xem Core Web Vitals và dung lượng CSS/JS của chính website hiện tại. <b>Không bắt buộc cấu hình</b>: nếu để trống CrUX API Key thì vẫn dùng được nhưng có thể bị giới hạn dữ liệu từ Google; URL cần đo mặc định là trang chủ website này.</div>'),
                Field::make('text', 'laca_crux_api_key', __('CrUX API Key', 'laca'))
                    ->set_width(50),
                Field::make('text', 'laca_crux_url', __('URL cần đo', 'laca'))
                    ->set_attribute('type', 'url')
                    ->set_default_value(home_url('/'))
                    ->set_width(50),
            ]);
    }

    private static function registerHelpContentOptions(Theme_Options_Container $root): void
    {
        Container::make('theme_options', __('Nội dung HD Sử dụng', 'laca'))
            ->set_page_parent($root)
            ->set_page_file(__('laca-help-content-settings', 'laca'))
            ->add_fields([
                Field::make('html', 'help_page_desc')
                    ->set_html('<div class="carbon-field-description">Nội dung này sẽ hiển thị ở menu <b>"HD Sử dụng"</b> dành cho khách hàng.</div>'),
                Field::make('text', 'help_page_title', __('Tiêu đề trang', 'laca'))
                    ->set_default_value('Hướng dẫn quản trị Website Professional'),
                Field::make('textarea', 'help_page_intro', __('Đoạn giới thiệu', 'laca'))
                    ->set_default_value('Chào mừng bạn đến với hệ thống quản trị website nâng cao. Hệ thống đã được tối ưu để bạn quản lý nội dung dễ dàng nhất.'),
                Field::make('complex', 'help_page_blocks', __('Các khối hướng dẫn (Blog, WooCommerce...)', 'laca'))
                    ->set_layout('tabbed-horizontal')
                    ->add_fields([
                        Field::make('text', 'title', __('Tiêu đề khối', 'laca')),
                        Field::make('color', 'border_color', __('Màu viền (Border top)', 'laca'))->set_default_value('#2271b1'),
                        Field::make('rich_text', 'content', __('Nội dung hướng dẫn (Link, Video, Text)', 'laca')),
                    ]),
                Field::make('separator', 'help_tours_separator', __('Tour hướng dẫn tương tác', 'laca')),
                Field::make('html', 'help_tours_desc')
                    ->set_html('<div class="carbon-field-description">Tạo các tour hướng dẫn theo từng bước. Nên dùng <strong>Link click / URL đích</strong> để chuyển qua màn hình kế tiếp, còn <strong>CSS selector</strong> chỉ dùng để highlight phần tử trên màn hình hiện tại. Nếu selector không tồn tại, hệ thống vẫn hiển thị nội dung bước thay vì dừng tour.</div>'),
                Field::make('complex', 'help_page_tours', __('Danh sách tour', 'laca'))
                    ->set_layout('tabbed-horizontal')
                    ->add_fields([
                        Field::make('text', 'title', __('Tên tour', 'laca'))
                            ->set_width(40),
                        Field::make('text', 'slug', __('Slug tour', 'laca'))
                            ->set_width(20)
                            ->set_help_text(__('Có thể để trống, hệ thống tự tạo từ tiêu đề.', 'laca')),
                        Field::make('text', 'admin_page', __('Trang admin đích', 'laca'))
                            ->set_width(40)
                            ->set_help_text(__('Ví dụ: index.php, upload.php, edit.php?post_type=page, themes.php?page=lacadev-control-center', 'laca')),
                        Field::make('textarea', 'description', __('Mô tả ngắn', 'laca'))
                            ->set_rows(2),
                        Field::make('complex', 'steps', __('Các bước', 'laca'))
                            ->set_layout('tabbed-horizontal')
                            ->add_fields([
                                Field::make('text', 'title', __('Tiêu đề bước', 'laca'))
                                    ->set_width(40),
                                Field::make('text', 'selector', __('CSS selector', 'laca'))
                                    ->set_width(30)
                                    ->set_help_text(__('Tuỳ chọn. Dùng để highlight phần tử hiện tại. Ví dụ: #menu-pages > a.menu-top, .row-title, .wrap h1, button[aria-label="Document Overview"]. Nên dùng dấu nháy thẳng " thay vì dấu ngoặc cong.', 'laca')),
                                Field::make('text', 'click_url', __('Link click / URL đích', 'laca'))
                                    ->set_width(50)
                                    ->set_help_text(__('Tuỳ chọn. Dùng khi bước này cần mở màn hình tiếp theo. Ví dụ: edit.php?post_type=page hoặc post.php?post=12&action=edit', 'laca')),
                                Field::make('select', 'position', __('Vị trí tooltip', 'laca'))
                                    ->set_width(20)
                                    ->set_options([
                                        'auto' => __('Tự động', 'laca'),
                                        'top' => __('Trên', 'laca'),
                                        'right' => __('Phải', 'laca'),
                                        'bottom' => __('Dưới', 'laca'),
                                        'left' => __('Trái', 'laca'),
                                    ])
                                    ->set_default_value('bottom'),
                                Field::make('textarea', 'content', __('Nội dung bước', 'laca'))
                                    ->set_rows(3),
                            ]),
                    ]),
                Field::make('separator', 'help_separator', __('Thông tin hỗ trợ kỹ thuật', 'laca')),
                Field::make('text', 'help_support_phone', __('Điện thoại/Zalo', 'laca')),
                Field::make('text', 'help_support_email', __('Email', 'laca')),
                Field::make('text', 'help_support_website', __('Website', 'laca')),
            ]);
    }

    private static function adminTabFields(): array
    {
        return [
            Field::make('checkbox', 'is_maintenance', __('Bật chế độ bảo trì', 'laca'))->set_width(30),
            Field::make('html', 'is_maintenance_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ bảo trì, tất cả người dùng sẽ không thể truy cập vào trang web của bạn. Bạn có thể tạm thời đóng băng trang web để tránh việc người dùng truy cập vào trang web của bạn.'),
            Field::make('checkbox', 'hide_theme_editor', __('Tắt chức năng chỉnh sửa code', 'laca'))->set_width(30),
            Field::make('html', 'hide_theme_editor_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể chỉnh sửa code trong trang admin.'),
            Field::make('checkbox', 'disable_admin_confirm_email', __('Tắt chức năng xác thực email khi thay đổi email admin', 'laca'))->set_width(30),
            Field::make('html', 'disable_admin_confirm_email_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không cần phải xác thực email khi thay đổi email admin.'),
            Field::make('checkbox', 'disable_use_weak_password', __('Tắt chức năng sử dụng mật khẩu yếu', 'laca'))->set_width(30),
            Field::make('html', 'disable_use_weak_password_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể sử dụng mật khẩu yếu.'),
            Field::make('checkbox', 'hide_post_menu_default', __('Ẩn menu bài viết mặc định', 'laca'))->set_width(30),
            Field::make('html', 'hide_post_menu_default_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bài viết trong trang admin.'),
            Field::make('checkbox', 'hide_comment_menu_default', __('Ẩn menu bình luận mặc định', 'laca'))->set_width(30),
            Field::make('html', 'hide_comment_menu_default_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bình luận trong trang admin.'),
        ];
    }

    private static function smtpTabFields(): array
    {
        return [
            Field::make('checkbox', 'use_smtp', __('Sử dụng SMTP để gửi mail', 'laca')),
            Field::make('separator', 'smtp_separator_1', __('Thông tin máy chủ SMTP', 'laca')),
            Field::make('text', 'smtp_host', __('Địa chỉ máy chủ', 'laca'))->set_width(33.33)->set_default_value('smtp.gmail.com'),
            Field::make('text', 'smtp_port', __('Cổng máy chủ', 'laca'))->set_width(33.33)->set_default_value('587'),
            Field::make('text', 'smtp_secure', __('Phương thức mã hóa', 'laca'))->set_width(33.33)->set_default_value('TLS'),
            Field::make('separator', 'smtp_separator_2', __('Thông tin email hệ thống', 'laca')),
            Field::make('text', 'smtp_username', __('Địa chỉ email', 'laca'))->set_width(50)->set_default_value('mooms.dev@gmail.com'),
            Field::make('text', 'smtp_password', __('Mật khẩu', 'laca'))
                ->set_width(50)
                ->set_attribute('type', 'password')
                ->set_attribute('data-field', 'password-field')
                ->set_default_value('utakxthdfibquxos'),
        ];
    }

    private static function loginTabFields(): array
    {
        return [
            Field::make('image', 'login_logo', __('Login logo', 'laca'))
                ->set_width(20)
                ->set_help_text('Nếu để trống sẽ dùng logo mặc định của website'),
            Field::make('textarea', 'login_welcome_text_vi', __('Lời chào (VI)', 'laca'))
                ->set_rows(4)
                ->set_width(40)
                ->set_default_value("Chào mừng về Trạm Laca!\nCắm sạc, pha trà và bắt đầu nào!")
                ->set_help_text('Có thể xuống dòng, hệ thống sẽ tự đổi sang <br/>'),
            Field::make('textarea', 'login_welcome_text_en', __('Welcome text (EN)', 'laca'))
                ->set_rows(4)
                ->set_width(40)
                ->set_default_value("Welcome to Laca Station!\nCharge up, brew some tea and let's go!"),
            Field::make('text', 'login_user_label_vi', __('Label user (VI)', 'laca'))->set_width(50)->set_default_value('Ai đang ghé trạm?'),
            Field::make('text', 'login_user_label_en', __('Label user (EN)', 'laca'))->set_width(50)->set_default_value("Who's visiting the station?"),
            Field::make('text', 'login_password_label_vi', __('Label password (VI)', 'laca'))->set_width(50)->set_default_value('Chìa khóa'),
            Field::make('text', 'login_password_label_en', __('Label password (EN)', 'laca'))->set_width(50)->set_default_value('The Key'),
            Field::make('text', 'login_user_placeholder_vi', __('Placeholder user (VI)', 'laca'))->set_width(50)->set_default_value('Điền tên hoặc email vào đây nhé'),
            Field::make('text', 'login_user_placeholder_en', __('Placeholder user (EN)', 'laca'))->set_width(50)->set_default_value('Enter name or email here'),
            Field::make('text', 'login_password_placeholder_vi', __('Placeholder password (VI)', 'laca'))->set_width(50)->set_default_value('Nhập chìa khóa mở cửa'),
            Field::make('text', 'login_password_placeholder_en', __('Placeholder password (EN)', 'laca'))->set_width(50)->set_default_value('Enter your key to open'),
            Field::make('text', 'login_forgot_label_vi', __('Label rớt chìa khoá (VI)', 'laca'))->set_width(50)->set_default_value('Rớt chìa khoá?'),
            Field::make('text', 'login_forgot_label_en', __('Forgot label (EN)', 'laca'))->set_width(50)->set_default_value('Lost your key?'),
            Field::make('text', 'login_back_label_vi', __('Label rời khỏi trạm (VI)', 'laca'))->set_width(50)->set_default_value('← Rời khỏi Trạm'),
            Field::make('text', 'login_back_label_en', __('Back label (EN)', 'laca'))->set_width(50)->set_default_value('← Leave the Station'),
        ];
    }

    private static function optimizationTabFields(): array
    {
        return [
            Field::make('separator', 'title_disable_unnecessary_items', __('Disable unnecessary items')),
            Field::make('checkbox', 'disable_use_jquery_migrate', __('Disable jQuery Migrate', 'laca'))->set_width(30),
            Field::make('html', 'disable_use_jquery_migrate_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> jQuery Migrate là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.'),
            Field::make('checkbox', 'disable_gutenberg_css', __('Disable Gutenberg CSS', 'laca'))->set_width(30),
            Field::make('html', 'gutenberg_css_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Gutenberg CSS là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.'),
            Field::make('checkbox', 'disable_classic_css', __('Disable Classic CSS', 'laca'))->set_width(30),
            Field::make('html', 'classic_css_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Classic CSS là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.'),
            Field::make('checkbox', 'disable_emoji', __('Disable Emoji', 'laca'))->set_width(30),
            Field::make('html', 'emoji_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Emoji là thư viện được sử dụng để hiển thị các biểu tượng trong trang web. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.'),
            Field::make('separator', 'title_optimization_library', __('Optimization Library')),
            Field::make('checkbox', 'enable_instant_page', __('Enable Instant-page', 'laca'))->set_width(30),
            Field::make('html', 'instant_page_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Instant-Page là một thư viện cho phép bạn tải trước nội dung của trang được liên kết vào bộ nhớ trình duyệt chỉ bằng cách di chuyển qua liên kết. Khi bạn nhấp vào liên kết, nó cung cấp trải nghiệm tải nhanh đáng kể'),
            Field::make('checkbox', 'enable_smooth_scroll', __('Enable Smooth-scroll', 'laca'))->set_width(30),
            Field::make('html', 'smooth_scroll_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Smooth-scroll là thư viện cho phép bạn tạo hiệu ứng cuộn mượt mà, cung cấp cho người dùng cảm giác điều hướng trang nhanh hơn.'),
            Field::make('separator', 'title_lazy_loading_images', __('The function of lazy loading images')),
            Field::make('html', 'lazy_loading_images_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Nếu bạn muốn lazy load hình ảnh mỗi khi trang tải, hãy bật tính năng này. Chức năng này giúp trang web của bạn tải nhanh hơn'),
            Field::make('checkbox', 'remove_comments', __('Remove comments from HTML, JavaScript, and CSS', 'laca')),
            Field::make('checkbox', 'remove_xhtml_closing_tags', __('Remove XHTML closing tags from empty elements in HTML5', 'laca')),
            Field::make('checkbox', 'remove_relative_domain', __('Remove relative domain from internal URLs', 'laca')),
            Field::make('checkbox', 'remove_protocols', __('Remove protocols (HTTP: and HTTPS:) from all URLs', 'laca')),
            Field::make('checkbox', 'support_multi_byte_utf_8', __('Support multi-byte UTF-8 encoding (if you see strange characters)', 'laca')),
            Field::make('checkbox', 'enable_advanced_resource_hints', __('Bật Advanced Resource Hints', 'laca'))->set_width(30),
            Field::make('html', 'enable_advanced_resource_hints_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Bật tính năng thêm resource hint (preload, preconnect,...) giúp tăng tốc tải tài nguyên.'),
            Field::make('checkbox', 'enable_optimize_images', __('Tối ưu hóa thuộc tính ảnh', 'laca'))->set_width(30),
            Field::make('html', 'enable_optimize_images_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tự động thêm lazy loading, alt, dimension cho ảnh.'),
            Field::make('checkbox', 'enable_optimize_content_images', __('Tối ưu hóa ảnh trong nội dung', 'laca'))->set_width(30),
            Field::make('html', 'enable_optimize_content_images_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tự động lazy load ảnh trong nội dung bài viết.'),
            Field::make('checkbox', 'enable_register_service_worker', __('Bật Service Worker cache', 'laca'))->set_width(30),
            Field::make('html', 'enable_register_service_worker_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Đăng ký service worker để tăng tốc tải trang và cache tài nguyên.'),
        ];
    }

    private static function securityTabFields(): array
    {
        return [
            Field::make('separator', 'title_enhance_website_security', __('Enhance website security')),
            Field::make('checkbox', 'disable_rest_api', __('Disable REST API', 'laca'))->set_width(30),
            Field::make('html', 'disable_rest_api_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> REST API mặc định trong WordPress cho phép ứng dụng bên ngoài giao tiếp với WordPress để lấy dữ liệu hoặc đăng nội dung, bạn nên vô hiệu hóa nó cho mục đích bảo mật.'),
            Field::make('checkbox', 'disable_xml_rpc', __('Disable XML RPC', 'laca'))->set_width(30),
            Field::make('html', 'disable_xml_rpc_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> XML-RPC là giao thức cho phép quản lý website từ xa thông qua ứng dụng như WordPress App hoặc Jetpack.<br> <b>Khuyến cáo:</b> Nên tắt hoàn toàn nếu không dùng tới.'),
            Field::make('checkbox', 'disable_wp_embed', __('Disable Wp-Embed', 'laca'))->set_width(30),
            Field::make('html', 'disable_wp_embed_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> WP-Embed cho phép nội dung của trang WordPress được nhúng vào trang web khác thông qua oEmbed.<br> <b>Khuyến cáo:</b> Nếu không dùng, nên tắt để giảm thiểu tải không cần thiết.'),
            Field::make('checkbox', 'disable_x_pingback', __('Disable X-Pingback', 'laca'))->set_width(30),
            Field::make('html', 'disable_x_pingback_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> X-Pingback là cơ chế thông báo giữa các blog (khi ai đó liên kết đến trang web).<br> <b>Khuyến cáo:</b> Nên tắt hoàn toàn nếu không dùng tới.'),
            Field::make('checkbox', 'enable_remove_wordpress_bloat', __('Loại bỏ bloat WordPress', 'laca'))->set_width(30),
            Field::make('html', 'enable_remove_wordpress_bloat_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Loại bỏ các thành phần không cần thiết của WordPress để tăng bảo mật và hiệu suất.'),
            Field::make('checkbox', 'enable_optimize_database_queries', __('Tối ưu hóa truy vấn database', 'laca'))->set_width(30),
            Field::make('html', 'enable_optimize_database_queries_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Giới hạn post revision, tăng autosave interval, bật object cache.'),
            Field::make('checkbox', 'enable_optimize_sql_queries', __('Log truy vấn SQL chậm', 'laca'))->set_width(30),
            Field::make('html', 'enable_optimize_sql_queries_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Log các truy vấn SQL chậm để phát hiện truy vấn bất thường.'),
            Field::make('checkbox', 'enable_optimize_memory_usage', __('Tối ưu hóa bộ nhớ', 'laca'))->set_width(30),
            Field::make('html', 'enable_optimize_memory_usage_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tăng memory limit, bật garbage collection.'),
            Field::make('checkbox', 'enable_cleanup_memory', __('Dọn dẹp bộ nhớ cuối trang', 'laca'))->set_width(30),
            Field::make('html', 'enable_cleanup_memory_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Dọn dẹp bộ nhớ cuối trang để giảm nguy cơ memory leak.'),
            Field::make('checkbox', 'enable_set_cache_headers', __('Đặt cache header nâng cao', 'laca'))->set_width(30),
            Field::make('html', 'enable_set_cache_headers_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Đặt cache header bảo vệ trang admin và user login.'),
            Field::make('checkbox', 'enable_compression', __('Bật gzip nén dữ liệu', 'laca'))->set_width(30),
            Field::make('html', 'enable_compression_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Bật gzip để bảo vệ dữ liệu truyền tải.'),
            Field::make('checkbox', 'enable_performance_monitoring', __('Giám sát hiệu suất', 'laca'))->set_width(30),
            Field::make('html', 'enable_performance_monitoring_desc')->set_width(70)->set_html('<i class="fa-regular fa-lightbulb-on"></i> Giám sát hiệu suất, phát hiện bất thường.'),
        ];
    }
}
