<?php

namespace App\Settings;

use App\Settings\Admin\AdminAccessDeniedPage;
use App\Settings\Admin\AdminAccessPolicy;
use App\Settings\Admin\AdminDashboardIntroWidget;
use App\Settings\Admin\AdminMediaSupport;
use App\Settings\Admin\AdminOptionHtml;
use App\Settings\Admin\AdminOptionsRegistrar;
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
			$this->bootRestrictedAdminExperience();
		}

		$this->bootSharedAdminUi();
		$this->bootMediaEnhancements();
		$this->applyOptionDrivenToggles();
	}

	private function bootRestrictedAdminExperience(): void
	{
		$this->hideSuperUsers();
		$this->setupErrorMessage();
		$this->checkIsMaintenance();
		$this->disablePluginPage();
		$this->disableOptionsReadPage();
		$this->disableAllUpdate();
		$this->removeUnnecessaryMenus();
	}

	private function bootSharedAdminUi(): void
	{
		$this->addDashboardContactWidget();
		$this->removeDefaultWidgets();
		$this->removeDashboardWidgets();
		$this->changeHeaderUrl();
		$this->changeHeaderTitle();
		$this->changeFooterCopyright();
		$this->customizeAdminBar();
	}

	private function bootMediaEnhancements(): void
	{
		$this->resizeOriginalImageAfterUpload();
		$this->renameUploadFileName();
		$this->addCustomExtensionsInMediaUpload();
		$this->enableMediaUploaderForHelpGuide();
		$this->registerHelpGuidePasteImageAjax();
	}

	private function applyOptionDrivenToggles(): void
	{
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
		// The intro panel is rendered on Laca Dashboard instead of wp-admin Dashboard.
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
			$deniePage = AdminAccessPolicy::deniedPluginScreens(get_option('_hide_theme_editor') === 'yes');
			$current_screen = get_current_screen();

			if ($current_screen !== null && in_array($current_screen->id, $deniePage, true)) {
				wp_die($errorMessage);
			}
		});
	}

	public function disableOptionsReadPage()
	{
		$removePages = AdminAccessPolicy::removedSettingsPages();
		add_action('admin_menu', static function () use ($removePages) {
			foreach ($removePages as $page) {
				remove_submenu_page('options-general.php', $page);
			}
		});

		$errorMessage = $this->errorMessage;
		$denyPages = AdminAccessPolicy::deniedSettingsScreens();
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
			$hidden_menus = AdminAccessPolicy::hiddenMenuSlugs($hide_comments);

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
		AdminOptionsRegistrar::register();
	}
}
