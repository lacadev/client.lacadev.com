<?php

namespace App\Settings;

use App\Settings\Admin\AdminAccessDeniedPage;
use App\Settings\Admin\AdminMediaSupport;
use App\Settings\Admin\AdminOptionHtml;
use Carbon_Fields\Container;
use Carbon_Fields\Field;
use Intervention\Image\ImageManagerStatic as Image;

class AdminSettings
{
	protected $currentUser;

	protected $errorMessage = '';

	public function __construct()
	{
		$this->currentUser = wp_get_current_user();

		// Luôn luôn đăng ký các options (Carbon Fields containers)
		// để front-end có thể đọc được bằng carbon_get_theme_option(),
		// sau đó mới áp dụng các giới hạn hiển thị cho non-super user.
		$this->createAdminOptions();

		if (!$this->isSuperUser()) {
			$this->hideSuperUsers();
			$this->setupErrorMessage();
			$this->checkIsMaintenance();
			$this->disablePluginPage();
			$this->disableOptionsReadPage();
			$this->disableAllUpdate();
			$this->removeUnnecessaryMenus();
		}

		$this->addDashboardContactWidget();
		$this->removeDefaultWidgets();
		$this->removeDashboardWidgets();
		$this->changeHeaderUrl();
		$this->changeHeaderTitle();
		$this->changeFooterCopyright();
		$this->customizeAdminBar();
		$this->resizeOriginalImageAfterUpload();
		$this->renameUploadFileName();
		$this->addCustomExtensionsInMediaUpload();
		$this->enableMediaUploaderForHelpGuide();
		$this->registerHelpGuidePasteImageAjax();

		if (get_option('_disable_admin_confirm_email') === 'yes') {
			$this->disableChangeAdminEmailRequireConfirm();
		}

		if (get_option('_disable_use_weak_password') === 'yes') {
			$this->disableCheckboxUseWeakPassword();
		}

		if (get_option('_hide_post_menu_default') === 'yes') {
			$this->hidePostMenuDefault();
		}

		if (get_option('_hide_comment_menu_default') === 'yes') {
			$this->hideCommentMenuDefault();
		}
	}

	public function addCustomExtensionsInMediaUpload()
	{
		add_filter('upload_mimes', static function ($mimes) {
			return AdminMediaSupport::allowedMimes($mimes);
		});

		add_action('wp_ajax_mm_get_attachment_url_thumbnail', static function () {
			$url          = '';
			$attachmentID = isset($_REQUEST['attachmentID']) ? $_REQUEST['attachmentID'] : '';
			if ($attachmentID) {
				$url = wp_get_attachment_url($attachmentID);
			}
			die($url);
		});
	}

	/**
	 * Enable WordPress media uploader and clipboard paste-to-upload on the
	 * "Quản trị & HD Sử dụng" Carbon Fields theme options screen.
	 */
	public function enableMediaUploaderForHelpGuide(): void
	{
		add_action('admin_enqueue_scripts', static function ($hook_suffix) {
			$page = isset($_GET['page']) ? (string) wp_unslash($_GET['page']) : '';
			$screen = function_exists('get_current_screen') ? get_current_screen() : null;
			$screenId = $screen && !empty($screen->id) ? (string) $screen->id : '';

			$isHelpGuideScreen = AdminMediaSupport::isHelpGuideScreen((string) $hook_suffix, $page, $screenId);

			if (!$isHelpGuideScreen) {
				return;
			}

			wp_enqueue_media();
			$theme_root_uri = dirname(get_template_directory_uri());
			$script_ver = wp_get_theme()->get('Version') ?: '1.0.0';
			wp_enqueue_script(
				'laca-help-guide-paste-image',
				$theme_root_uri . '/resources/scripts/admin/help-guide-paste-image.js',
				['jquery'],
				$script_ver,
				true
			);
			wp_localize_script(
				'laca-help-guide-paste-image',
				'lacaHelpPasteImage',
				AdminMediaSupport::pasteImageConfig(
					admin_url('admin-ajax.php'),
					wp_create_nonce('laca_help_paste_image'),
					static fn(string $text): string => __($text, 'laca')
				)
			);
		}, 20);
	}

	/**
	 * AJAX handler: upload pasted image from help guide editor to Media Library.
	 */
	public function registerHelpGuidePasteImageAjax(): void
	{
		add_action('wp_ajax_laca_help_paste_image', static function () {
			if (!check_ajax_referer('laca_help_paste_image', 'nonce', false)) {
				wp_send_json_error(['message' => __('Nonce không hợp lệ.', 'laca')], 403);
			}

			if (!current_user_can('upload_files')) {
				wp_send_json_error(['message' => __('Bạn không có quyền upload media.', 'laca')], 403);
			}

			if (empty($_FILES['image'])) {
				wp_send_json_error(['message' => __('Không tìm thấy file ảnh từ clipboard.', 'laca')], 400);
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachmentId = media_handle_upload('image', 0);
			if (is_wp_error($attachmentId)) {
				wp_send_json_error(['message' => $attachmentId->get_error_message()], 400);
			}

			$imageUrl = wp_get_attachment_url($attachmentId);
			if (!$imageUrl) {
				wp_send_json_error(['message' => __('Upload thành công nhưng không lấy được URL ảnh.', 'laca')], 500);
			}

			wp_send_json_success([
				'id'  => $attachmentId,
				'url' => $imageUrl,
			]);
		});
	}

	public function disableCheckboxUseWeakPassword()
	{
		add_action('admin_enqueue_scripts', function () {
			wp_enqueue_script('jquery');
			wp_add_inline_script(
				'jquery',
				'jQuery(document).ready(function($) { $(".pw-weak").remove(); });'
			);
		});

		add_action('login_enqueue_scripts', function () {
			wp_enqueue_script(
				'laca-remove-pw-weak',
				get_template_directory_uri() . '/resources/scripts/login/remove-pw-weak.js',
				[],
				wp_get_theme()->get('Version'),
				true
			);
		});
	}

	public function addDashboardContactWidget()
	{
		add_action('wp_dashboard_setup', static function () {
			if (function_exists('lacadev_dashboard_widget_enabled') && !lacadev_dashboard_widget_enabled('contact_intro')) {
				return;
			}

			wp_add_dashboard_widget('custom_help_widget', 'Giới thiệu', static function () { ?>
				<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 0;">
					<a target="_blank" href="<?php echo AUTHOR['website'] ?>" title="<?php echo AUTHOR['name'] ?>" style="opacity: 0.9; transition: opacity 0.2s;">
						<img style="max-width: 160px; height: auto; display: block;" src="<?php echo get_site_url() . '/wp-content/themes/lacadev/resources/images/dev/moomsdev-black.png' ?>" alt="<?php echo AUTHOR['name'] ?>">
					</a>
					<div style="margin-top: 20px; text-align: center;">
						
						<p style="margin: 0 0 15px; font-size: 16px; font-style: italic; color: #b5b5b5; font-family: 'Quicksand', sans-serif; font-weight: 500;">
							"Coding amidst the journeys"
						</p>

						<div style="display: flex; gap: 12px; justify-content: center; align-items: center; font-size: 14px; color: #848383; font-family: 'Quicksand', sans-serif; font-weight: 600;">
							<a style="color: inherit; text-decoration: none;" href="tel:<?php echo str_replace(['.', ',', ' '], '', AUTHOR['phone_number']); ?>" target="_blank">
								<?php echo AUTHOR['phone_number'] ?>
							</a>
							<span style="color: #dcdcde;">|</span>
							<a style="color: inherit; text-decoration: none;" href="mailto:<?php echo AUTHOR['email'] ?>" target="_blank">
								<?php echo AUTHOR['email'] ?>
							</a>
							<span style="color: #dcdcde;">|</span>
							<a style="color: inherit; text-decoration: none;" href="<?php echo AUTHOR['website'] ?>" target="_blank">
								Ghé thăm tôi
							</a>
						</div>
					</div>
				</div>
<?php });
		});
	}

	public function removeDefaultWidgets()
	{
		add_action('widgets_init', static function () {
			unregister_widget('WP_Widget_Pages');
			unregister_widget('WP_Widget_Calendar');
			unregister_widget('WP_Widget_Archives');
			unregister_widget('WP_Widget_Links');
			unregister_widget('WP_Widget_Meta');
			unregister_widget('WP_Widget_Search');
			unregister_widget('WP_Widget_Categories');
			unregister_widget('WP_Widget_Recent_Posts');
			unregister_widget('WP_Widget_Recent_Comments');
			unregister_widget('WP_Widget_RSS');
			unregister_widget('WP_Widget_Tag_Cloud');
			unregister_widget('WP_Nav_Menu_Widget');
		});
	}
	public function removeDashboardWidgets()
	{
		add_action('admin_init', static function () {
			remove_meta_box('dashboard_right_now', 'dashboard', 'normal');       // right now
			remove_meta_box('dashboard_activity', 'dashboard', 'normal');        // WP 3.8
			remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal'); // recent comments
			remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');  // incoming links
			remove_meta_box('dashboard_plugins', 'dashboard', 'normal');         // plugins
			remove_meta_box('dashboard_quick_press', 'dashboard', 'normal');     // quick press
			remove_meta_box('dashboard_recent_drafts', 'dashboard', 'normal');   // recent drafts
			remove_meta_box('dashboard_primary', 'dashboard', 'normal');         // wordpress blog
			remove_meta_box('dashboard_secondary', 'dashboard', 'normal');       // other wordpress news
		});
	}

	public function changeHeaderUrl()
	{
		add_filter('login_headerurl', static function ($url) {
			return '' . AUTHOR['website'] . '';
		});
	}

	public function changeHeaderTitle()
	{
		add_filter('login_headertext', static function () {
			return get_option('blogname');
		});
	}

	public function changeFooterCopyright()
	{
		add_filter('admin_footer_text', static function () {
			echo '<a href="' . AUTHOR['website'] . '" target="_blank">' . AUTHOR['name'] . '</a> © ' . date('Y') . ' - Coding amidst the journeys';
		});
	}

	public function customizeAdminBar()
	{
		$author = AUTHOR;
		add_action('wp_before_admin_bar_render', static function () use ($author) {
			global $wp_admin_bar;
			$hide_comments = get_option('_hide_comment_menu_default') === 'yes';
			$hide_comments = (bool) apply_filters('lacadev_hide_comments_menu', $hide_comments);

			$wp_admin_bar->remove_menu('wp-logo');          // Remove the Wordpress logo
			$wp_admin_bar->remove_menu('about');            // Remove the about Wordpress link
			$wp_admin_bar->remove_menu('wporg');            // Remove the Wordpress.org link
			$wp_admin_bar->remove_menu('documentation');    // Remove the Wordpress documentation link
			$wp_admin_bar->remove_menu('support-forums');   // Remove the support forums link
			$wp_admin_bar->remove_menu('feedback');         // Remove the feedback link
			// $wp_admin_bar->remove_menu('site-name');        // Remove the site name menu
			$wp_admin_bar->remove_menu('view-site');        // Remove the view site link
			$wp_admin_bar->remove_menu('updates');          // Remove the updates link
			$wp_admin_bar->remove_menu('new-content');      // Remove the content link
			$wp_admin_bar->remove_menu('w3tc');             // If you use w3 total cache remove the performance link

			if ($hide_comments) {
				$wp_admin_bar->remove_menu('comments');
			}
			// $wp_admin_bar->remove_menu('my-account');       // Remove the user details tab
		}, 7);

		add_action('admin_bar_menu', static function ($wp_admin_bar) use ($author) {
			$args = [
				'id'    => 'logo_author',
				'title' => '<img src="' . get_site_url() . "/wp-content/themes/lacadev/resources/images/dev/moomsdev-white.png" . '" class="logo-admin-bar" alt="' . AUTHOR['name'] . '">',
				'href'  => $author['website'],
				'meta'  => [
					'target' => '_blank',
				],
			];
			$wp_admin_bar->add_node($args);
		}, 10);
	}

	public function renameUploadFileName()
	{
		add_filter('sanitize_file_name', function ($filename) {
			return AdminMediaSupport::sanitizeUploadFilename($filename, date('YmdHi'));
		}, 10);
	}

	public function resizeOriginalImageAfterUpload()
	{
		add_filter('intermediate_image_sizes_advanced', static function ($sizes) {
			$imgSize = [
				'medium',
				'medium_large',
				'large',
				'full',
				'woocommerce_single',
				'woocommerce_gallery_thumbnail',
				'shop_catalog',
				'shop_single',
				'woocommerce_thumbnail',
				'shop_thumbnail',
			];
			foreach ($imgSize as $item) {
				if (array_key_exists($item, $sizes)) {
					unset($sizes[$item]);
				}
			}
			return $sizes;
		});

		add_filter('wp_generate_attachment_metadata', static function ($image_data) {
			try {
				$upload_dir = wp_upload_dir();
				$imgPath    = $upload_dir['basedir'] . '/' . $image_data['file'];
				$image      = Image::make($imgPath);
				$imgWidth   = $image->width();
				$imgHeight  = $image->height();
				$image->resize(null, null, static function ($constraint) {
					$constraint->aspectRatio();
				});
				$image->save($imgPath, 100);
			} catch (\Exception $ex) {
			}
			return $image_data;
		});
	}

	public function disableChangeAdminEmailRequireConfirm()
	{
		remove_action('add_option_new_admin_email', 'update_option_new_admin_email');
		remove_action('update_option_new_admin_email', 'update_option_new_admin_email');

		add_action('add_option_new_admin_email', function ($old_value, $value) {
			update_option('admin_email', $value);
		}, 10, 2);

		add_action('update_option_new_admin_email', function ($old_value, $value) {
			update_option('admin_email', $value);
		}, 10, 2);
	}

	public function hideSuperUsers()
	{
		add_action('pre_user_query', function ($user_search) {
			if ($this->isSuperUser()) {
				return;
			}
			
			global $wpdb;
			$super_logins = apply_filters('lacadev_super_user_logins', ['lacadev']);
			$super_users_str = "('" . implode("','", array_map('esc_sql', $super_logins)) . "')";
			$user_search->query_where = str_replace('WHERE 1=1', "WHERE 1=1 AND {$wpdb->users}.user_login NOT IN " . $super_users_str, $user_search->query_where);
		});
	}

	/**
	 * Check if current user is a super user (Developer)
	 * 
	 * @return bool
	 */
	protected function isSuperUser()
	{
		$super_logins = apply_filters('lacadev_super_user_logins', ['lacadev']);
		$is_super     = in_array($this->currentUser->user_login, $super_logins, true);
		
		return apply_filters('lacadev_is_super_user', $is_super, $this->currentUser);
	}

	public function setupErrorMessage()
	{
		$this->errorMessage = AdminAccessDeniedPage::render(
			get_site_url() . '/wp-content/themes/lacadev/resources/images/dev/moomsdev-black.png',
			admin_url(),
			AUTHOR['website'],
			AUTHOR['name']
		);
	}

	public function checkIsMaintenance()
	{
        // Sử dụng template_redirect để chỉ ảnh hưởng Frontend
        // Không ảnh hưởng wp-admin hoặc wp-login.php
		add_action('template_redirect', static function () {
            // 1. Kiểm tra option có đang bật không
			if (get_option('_is_maintenance') === 'yes') {
                
                // 2. Nếu là Admin hoặc Editor thì CHO PHÉP truy cập để làm việc
                if (current_user_can('edit_theme_options')) {
                    return;
                }

                // 3. Chặn tất cả user khác và load template báo trì
                // Sử dụng status_header + exit thay vì wp_die để render full custom UI
                status_header(503);
                nocache_headers();
                include get_template_directory() . '/maintenance.php';
                exit();
			}
		});
	}

	public function disablePluginPage()
	{
		add_action('admin_menu', static function () {
			global $menu;
			foreach ($menu as $key => $menuItem) {
				switch ($menuItem[2]) {
					case 'plugins.php':
					case 'customize.php':
						// case 'themes.php':
						unset($menu[$key]);
						break;
				}
			}

			global $submenu;
			unset($submenu['themes.php'][5], $submenu['themes.php'][6]);

			if (get_option('_hide_theme_editor') === 'yes') {
				unset($submenu['themes.php'][11]);
				remove_submenu_page('themes.php', 'theme-editor.php');
			}
		}, 999);

		$errorMessage = $this->errorMessage;
		add_action('current_screen', static function () use ($errorMessage) {
			$deniePage      = [
				'plugins',
				'plugin-install',
				'plugin-editor',
				'themes',
				'theme-install',
				'theme-install',
				'customize',
				'customize',
				'tools',
				'import',
				'export',
				'tools_page_action-scheduler',
				'tools_page_export_personal_data',
				'tools_page_export_personal_data',
				'tools_page_remove_personal_data',
			];
			if (get_option('_hide_theme_editor') === 'yes') {
				$deniePage[] = 'theme-editor';
			}
			$current_screen = get_current_screen();

			if ($current_screen !== null && in_array($current_screen->id, $deniePage, true)) {
				wp_die($errorMessage);
			}
		});
	}

	public function disableOptionsReadPage()
	{
		$removePages = [
			'options-reading.php',
			'options-writing.php',
			'options-discussion.php',
			'options-media.php',
			'privacy.php',
			'options-permalink.php',
			'tinymce-advanced',
		];
		add_action('admin_menu', static function () use ($removePages) {
			foreach ($removePages as $page) {
				remove_submenu_page('options-general.php', $page);
			}
		});

		$errorMessage = $this->errorMessage;
		$denyPages    = [
			'options-reading',
			'options-writing',
			'options-discussion',
			'options-media',
			'privacy',
			'options-permalink',
			'settings_page_tinymce-advanced',
			'toplevel_page_wpseo_dashboard',
		];
		add_action('current_screen', static function () use ($errorMessage, $denyPages) {
			$current_screen = get_current_screen();
			if ($current_screen !== null && in_array($current_screen->id, $denyPages, true)) {
				wp_die($errorMessage);
			}
		});
	}

	public function disableAllUpdate()
	{
		remove_action('load-update-core.php', 'wp_update_plugins');
		add_filter('pre_site_transient_update_plugins', function ($a) {
			return null;
		});
	}

	public function removeUnnecessaryMenus()
	{
		add_action('admin_menu', static function () {
			global $menu;
			global $submenu;
			$hide_comments = get_option('_hide_comment_menu_default') === 'yes';
			$hide_comments = (bool) apply_filters('lacadev_hide_comments_menu', $hide_comments);
			$hidden_menus = [
				'tools.php',
				'wpseo_dashboard',
				'duplicator',
				'yit_plugin_panel',
				'woocommerce-checkout-manager',
			];

			if ($hide_comments) {
				$hidden_menus[] = 'edit-comments.php';
			}

			foreach ($menu as $key => $menuItem) {
				if (in_array($menuItem[2], $hidden_menus, true)) {
					unset($menu[$key]);
				}
			}
		});
	}

	public function hidePostMenuDefault()
	{
		add_action('admin_init', function () {
			remove_menu_page('edit.php');
		});
	}

	public function hideCommentMenuDefault()
	{
		add_action('admin_init', function () {
			remove_menu_page('edit-comments.php');
		});
	}

	public function createAdminOptions()
	{
		add_action('carbon_fields_register_fields', static function () {
			$options = Container::make('theme_options', __('Laca Admin', 'laca'))
				->set_page_file(__('laca-admin', 'laca'))
				->set_page_menu_position(3)
				->add_tab(__('ADMIN', 'laca'), [
					Field::make('checkbox', 'is_maintenance', __('Bật chế độ bảo trì', 'laca')) 
						->set_width(30),
					Field::make( 'html', 'is_maintenance_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ bảo trì, tất cả người dùng sẽ không thể truy cập vào trang web của bạn. Bạn có thể tạm thời đóng băng trang web để tránh việc người dùng truy cập vào trang web của bạn.' ),
					
					// hide theme editor
					Field::make('checkbox', 'hide_theme_editor', __('Tắt chức năng chỉnh sửa code', 'laca'))
					->set_width(30),
					Field::make( 'html', 'hide_theme_editor_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể chỉnh sửa code trong trang admin.' ),

					Field::make('checkbox', 'disable_admin_confirm_email', __('Tắt chức năng xác thực email khi thay đổi email admin', 'laca'))
						->set_width(30),
					Field::make( 'html', 'disable_admin_confirm_email_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không cần phải xác thực email khi thay đổi email admin.' ),
					
					Field::make('checkbox', 'disable_use_weak_password', __('Tắt chức năng sử dụng mật khẩu yếu', 'laca'))
						->set_width(30),
					Field::make( 'html', 'disable_use_weak_password_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể sử dụng mật khẩu yếu.' ),

					Field::make('checkbox', 'hide_post_menu_default', __('Ẩn menu bài viết mặc định', 'laca'))
						->set_width(30),
					Field::make( 'html', 'hide_post_menu_default_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bài viết trong trang admin.' ),

					Field::make('checkbox', 'hide_comment_menu_default', __('Ẩn menu bình luận mặc định', 'laca'))
						->set_width(30),
					Field::make( 'html', 'hide_comment_menu_default_desc' )
						->set_width(70)
						->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Khi bật chế độ này, bạn sẽ không thể xem menu bình luận trong trang admin.' ),
						
				])
				->add_tab(__('SMTP', 'laca'), [
					Field::make('checkbox', 'use_smtp', __('Sử dụng SMTP để gửi mail', 'laca')),
					
					Field::make('separator', 'smtp_separator_1', __('Thông tin máy chủ SMTP', 'laca')),
					Field::make('text', 'smtp_host', __('Địa chỉ máy chủ', 'laca'))
						->set_width(33.33)
						->set_default_value('smtp.gmail.com'),
					Field::make('text', 'smtp_port', __('Cổng máy chủ', 'laca'))
						->set_width(33.33)
						->set_default_value('587'),
					Field::make('text', 'smtp_secure', __('Phương thức mã hóa', 'laca'))
						->set_width(33.33)
						->set_default_value('TLS'),

					Field::make('separator', 'smtp_separator_2', __('Thông tin email hệ thống', 'laca')),
					Field::make('text', 'smtp_username', __('Địa chỉ email', 'laca'))
						->set_width(50)
						->set_default_value('mooms.dev@gmail.com'),
					Field::make('text', 'smtp_password', __('Mật khẩu', 'laca'))
						->set_width(50)
						->set_attribute('type', 'password')
						->set_attribute('data-field', 'password-field')
						->set_default_value('utakxthdfibquxos'),
				])
				->add_tab(__('LOGIN', 'laca'), [
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

					Field::make('text', 'login_user_label_vi', __('Label user (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Ai đang ghé trạm?'),
					Field::make('text', 'login_user_label_en', __('Label user (EN)', 'laca'))
						->set_width(50)
						->set_default_value("Who's visiting the station?"),

					Field::make('text', 'login_password_label_vi', __('Label password (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Chìa khóa'),
					Field::make('text', 'login_password_label_en', __('Label password (EN)', 'laca'))
						->set_width(50)
						->set_default_value('The Key'),

					Field::make('text', 'login_user_placeholder_vi', __('Placeholder user (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Điền tên hoặc email vào đây nhé'),
					Field::make('text', 'login_user_placeholder_en', __('Placeholder user (EN)', 'laca'))
						->set_width(50)
						->set_default_value('Enter name or email here'),

					Field::make('text', 'login_password_placeholder_vi', __('Placeholder password (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Nhập chìa khóa mở cửa'),
					Field::make('text', 'login_password_placeholder_en', __('Placeholder password (EN)', 'laca'))
						->set_width(50)
						->set_default_value('Enter your key to open'),

					Field::make('text', 'login_forgot_label_vi', __('Label rớt chìa khoá (VI)', 'laca'))
						->set_width(50)
						->set_default_value('Rớt chìa khoá?'),
					Field::make('text', 'login_forgot_label_en', __('Forgot label (EN)', 'laca'))
						->set_width(50)
						->set_default_value('Lost your key?'),

					Field::make('text', 'login_back_label_vi', __('Label rời khỏi trạm (VI)', 'laca'))
						->set_width(50)
						->set_default_value('← Rời khỏi Trạm'),
					Field::make('text', 'login_back_label_en', __('Back label (EN)', 'laca'))
						->set_width(50)
						->set_default_value('← Leave the Station'),
				]);

			Container::make('theme_options', __('Tools', 'laca'))
			->set_page_parent($options)
			->set_page_file(__('laca-tools', 'laca'))
			->add_tab(__('Optimization', 'laca'), [
				// Disable unnecessary items
				Field::make( 'separator', 'title_disable_unnecessary_items', __( 'Disable unnecessary items' ) ),
				Field::make('checkbox', 'disable_use_jquery_migrate', __('Disable jQuery Migrate', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_use_jquery_migrate_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> jQuery Migrate là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
					
				Field::make('checkbox', 'disable_gutenberg_css', __('Disable Gutenberg CSS', 'laca'))
					->set_width(30),
				Field::make( 'html', 'gutenberg_css_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Gutenberg CSS là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
					
				Field::make('checkbox', 'disable_classic_css', __('Disable Classic CSS', 'laca'))
					->set_width(30),
				Field::make( 'html', 'classic_css_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Classic CSS là thư viện được sử dụng để duy trì hoạt động của các plugin và theme cũ. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
					
				Field::make('checkbox', 'disable_emoji', __('Disable Emoji', 'laca'))
					->set_width(30),
				Field::make( 'html', 'emoji_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Emoji là thư viện được sử dụng để hiển thị các biểu tượng trong trang web. Nếu bạn không sử dụng plugin này, bạn có thể tắt nó để tăng tốc độ tải trang.' ),
				
				// Optimization Library
				Field::make( 'separator', 'title_optimization_library', __( 'Optimization Library' ) ),
				Field::make('checkbox', 'enable_instant_page', __('Enable Instant-page', 'laca'))
					->set_width(30),
				Field::make( 'html', 'instant_page_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Instant-Page là một thư viện cho phép bạn tải trước nội dung của trang được liên kết vào bộ nhớ trình duyệt chỉ bằng cách di chuyển qua liên kết. Khi bạn nhấp vào liên kết, nó cung cấp trải nghiệm tải nhanh đáng kể' ),
					
				Field::make('checkbox', 'enable_smooth_scroll', __('Enable Smooth-scroll', 'laca'))
					->set_width(30),
				Field::make( 'html', 'smooth_scroll_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Smooth-scroll là thư viện cho phép bạn tạo hiệu ứng cuộn mượt mà, cung cấp cho người dùng cảm giác điều hướng trang nhanh hơn.' ),
					
				// The function of lazy loading images
				Field::make( 'separator', 'title_lazy_loading_images', __( 'The function of lazy loading images' ) ),
				Field::make( 'html', 'lazy_loading_images_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> Nếu bạn muốn lazy load hình ảnh mỗi khi trang tải, hãy bật tính năng này. Chức năng này giúp trang web của bạn tải nhanh hơn' ),

				Field::make('checkbox', 'remove_comments', __('Remove comments from HTML, JavaScript, and CSS', 'laca')),
				Field::make('checkbox', 'remove_xhtml_closing_tags', __('Remove XHTML closing tags from empty elements in HTML5', 'laca')),
				Field::make('checkbox', 'remove_relative_domain', __('Remove relative domain from internal URLs', 'laca')),
				Field::make('checkbox', 'remove_protocols', __('Remove protocols (HTTP: and HTTPS:) from all URLs', 'laca')),
				Field::make('checkbox', 'support_multi_byte_utf_8', __('Support multi-byte UTF-8 encoding (if you see strange characters)', 'laca')),
				// Thêm các field tối ưu hóa mới
				Field::make('checkbox', 'enable_advanced_resource_hints', __('Bật Advanced Resource Hints', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_advanced_resource_hints_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Bật tính năng thêm resource hint (preload, preconnect,...) giúp tăng tốc tải tài nguyên.'),

				Field::make('checkbox', 'enable_optimize_images', __('Tối ưu hóa thuộc tính ảnh', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_images_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tự động thêm lazy loading, alt, dimension cho ảnh.'),

				Field::make('checkbox', 'enable_optimize_content_images', __('Tối ưu hóa ảnh trong nội dung', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_content_images_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tự động lazy load ảnh trong nội dung bài viết.'),

				Field::make('checkbox', 'enable_register_service_worker', __('Bật Service Worker cache', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_register_service_worker_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Đăng ký service worker để tăng tốc tải trang và cache tài nguyên.'),
			])
			// Security
			->add_tab(__('Security', 'laca'), [
				// Enhance website security
				Field::make( 'separator', 'title_enhance_website_security', __( 'Enhance website security' ) ),
				Field::make('checkbox', 'disable_rest_api', __('Disable REST API', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_rest_api_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> REST API mặc định trong WordPress cho phép ứng dụng bên ngoài giao tiếp với WordPress để lấy dữ liệu hoặc đăng nội dung, bạn nên vô hiệu hóa nó cho mục đích bảo mật.' ),

				Field::make('checkbox', 'disable_xml_rpc', __('Disable XML RPC', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_xml_rpc_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> XML-RPC là giao thức cho phép quản lý website từ xa thông qua ứng dụng như WordPress App hoặc Jetpack.<br> <b>Khuyến cáo:</b> Nên tắt hoàn toàn nếu không dùng tới.' ),

				Field::make('checkbox', 'disable_wp_embed', __('Disable Wp-Embed', 'laca'))
					->set_width(30),	
				Field::make( 'html', 'disable_wp_embed_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> WP-Embed cho phép nội dung của trang WordPress được nhúng vào trang web khác thông qua oEmbed.<br> <b>Khuyến cáo:</b> Nếu không dùng, nên tắt để giảm thiểu tải không cần thiết.' ),

				Field::make('checkbox', 'disable_x_pingback', __('Disable X-Pingback', 'laca'))
					->set_width(30),
				Field::make( 'html', 'disable_x_pingback_desc' )
					->set_width(70)
					->set_html( '<i class="fa-regular fa-lightbulb-on"></i> X-Pingback là cơ chế thông báo giữa các blog (khi ai đó liên kết đến trang web).<br> <b>Khuyến cáo:</b> Nên tắt hoàn toàn nếu không dùng tới.' ),
					
				// Thêm các field bảo mật mới
				Field::make('checkbox', 'enable_remove_wordpress_bloat', __('Loại bỏ bloat WordPress', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_remove_wordpress_bloat_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Loại bỏ các thành phần không cần thiết của WordPress để tăng bảo mật và hiệu suất.'),

				Field::make('checkbox', 'enable_optimize_database_queries', __('Tối ưu hóa truy vấn database', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_database_queries_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Giới hạn post revision, tăng autosave interval, bật object cache.'),

				Field::make('checkbox', 'enable_optimize_sql_queries', __('Log truy vấn SQL chậm', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_sql_queries_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Log các truy vấn SQL chậm để phát hiện truy vấn bất thường.'),

				Field::make('checkbox', 'enable_optimize_memory_usage', __('Tối ưu hóa bộ nhớ', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_optimize_memory_usage_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Tăng memory limit, bật garbage collection.'),

				Field::make('checkbox', 'enable_cleanup_memory', __('Dọn dẹp bộ nhớ cuối trang', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_cleanup_memory_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Dọn dẹp bộ nhớ cuối trang để giảm nguy cơ memory leak.'),

				Field::make('checkbox', 'enable_set_cache_headers', __('Đặt cache header nâng cao', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_set_cache_headers_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Đặt cache header bảo vệ trang admin và user login.'),

				Field::make('checkbox', 'enable_compression', __('Bật gzip nén dữ liệu', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_compression_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Bật gzip để bảo vệ dữ liệu truyền tải.'),

				Field::make('checkbox', 'enable_performance_monitoring', __('Giám sát hiệu suất', 'laca'))
					->set_width(30),
				Field::make('html', 'enable_performance_monitoring_desc')
					->set_width(70)
					->set_html('<i class="fa-regular fa-lightbulb-on"></i> Giám sát hiệu suất, phát hiện bất thường.'),
			]);

			// LacaDev Block Sync
			Container::make('theme_options', __('🧩 LacaDev', 'laca'))
				->set_page_parent($options)
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

			// Tracker Settings — kết nối gửi log về lacadev CMS
			Container::make('theme_options', __('📡 Tracker', 'laca'))
				->set_page_parent($options)
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

			// Google reCAPTCHA

			Container::make('theme_options', __('Google reCAPTCHA', 'laca'))
				->set_page_parent($options)
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

			// LacaDev Project Notifications
			Container::make('theme_options', __('LacaDev PM & Bots', 'laca'))
				->set_page_parent($options)
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

            Container::make('theme_options', __('Login Socials', 'laca'))
            ->set_page_parent($options)
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

            // Workspace / HD Sử dụng & Dashboard Widgets Settings
            Container::make('theme_options', __('Dashboard Widgets', 'laca'))
                ->set_page_parent($options)
                ->set_page_file(__('laca-management-dashboard-widgets', 'laca'))
                ->add_fields([
                    Field::make('html', 'dashboard_widgets_desc')
                        ->set_html('<div class="carbon-field-description">Chọn widget custom nào được hiển thị ngoài màn hình Dashboard. Widget không được chọn sẽ không đăng ký ra Dashboard.</div>'),

                    Field::make('multiselect', 'dashboard_widgets_enabled', __('Widget hiển thị ngoài Dashboard', 'laca'))
                        ->set_options(function() {
                            return function_exists('lacadev_dashboard_widget_definitions')
                                ? lacadev_dashboard_widget_definitions()
                                : [];
                        })
                        ->set_default_value(function_exists('lacadev_dashboard_widget_definitions') ? array_keys(lacadev_dashboard_widget_definitions()) : [])
                        ->set_help_text(__('Chỉ những widget được chọn mới hiển thị ở Dashboard chính.', 'laca')),

					Field::make('separator', 'content_report_separator', __('Widget báo cáo nội dung', 'laca')),
                    Field::make('multiselect', 'dashboard_widget_post_types', __('Các Post Type hiển thị', 'laca'))
                        ->set_options(function() {
                            $types = get_post_types(['public' => true, 'show_in_menu' => true], 'objects');
                            $options = [];
                            foreach ($types as $pt) {
                                if (in_array($pt->name, ['attachment', 'wp_block', 'wp_template', 'wp_template_part'])) continue;
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

            Container::make('theme_options', __('Nội dung HD Sử dụng', 'laca'))
                ->set_page_parent($options)
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

                    Field::make('separator', 'help_separator', __('Thông tin hỗ trợ kỹ thuật', 'laca')),
                    Field::make('text', 'help_support_phone', __('Điện thoại/Zalo', 'laca')),
                    Field::make('text', 'help_support_email', __('Email', 'laca')),
                    Field::make('text', 'help_support_website', __('Website', 'laca')),
                ]);
        });
	}
}
