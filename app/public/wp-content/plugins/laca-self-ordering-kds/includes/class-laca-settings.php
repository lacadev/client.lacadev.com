<?php
/**
 * Plugin settings and automatic menu page management.
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores operational settings in wp_options and manages the menu page QR.
 */
class Laca_KDS_Settings {
	const OPTION_KEY          = 'laca_kds_settings';
	const MENU_PAGE_OPTION    = 'laca_kds_menu_page_id';
	const STATUS_PAGE_OPTION  = 'laca_kds_status_page_id';
	const PICKUP_PAGE_OPTION  = 'laca_kds_pickup_page_id';
	const MENU_PAGE_PENDING   = 'laca_kds_menu_page_pending_creation';
	const MENU_PAGE_TEMPLATE  = 'laca-menu-app-template.php';
	const STATUS_PAGE_TEMPLATE = 'laca-order-status-template.php';
	const PICKUP_PAGE_TEMPLATE = 'laca-pickup-screen-template.php';
	const MENU_PAGE_META_KEY  = '_laca_kds_auto_menu_page';
	const PUBLIC_PAGE_ROLE_META = '_laca_kds_public_page_role';
	const SETTINGS_PAGE_SLUG  = 'laca-kds-settings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		self::maybe_initialize_settings();

		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_scheduled_menu_page' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ), 20 );
		add_action( 'admin_post_laca_kds_sync_menu_page', array( __CLASS__, 'handle_sync_menu_page' ) );
		add_action( 'wp_ajax_laca_kds_test_notification', array( __CLASS__, 'ajax_test_notification' ) );
		add_filter( 'theme_page_templates', array( __CLASS__, 'register_page_template' ) );
		add_filter( 'template_include', array( __CLASS__, 'load_page_template' ) );
	}

	/**
	 * Schedule automatic page creation for a safe admin lifecycle hook.
	 *
	 * @return void
	 */
	public static function schedule_menu_page_creation() {
		update_option( self::MENU_PAGE_PENDING, '1', false );
	}

	/**
	 * Create the menu page after WordPress has initialized rewrite/permalink APIs.
	 *
	 * @return void
	 */
	public static function maybe_create_scheduled_menu_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if (
			'1' !== get_option( self::MENU_PAGE_PENDING )
			&& self::get_menu_page_id()
			&& self::get_public_page_id( self::STATUS_PAGE_OPTION )
			&& self::get_public_page_id( self::PICKUP_PAGE_OPTION )
		) {
			return;
		}

		self::maybe_create_public_pages();
		delete_option( self::MENU_PAGE_PENDING );
	}

	/**
	 * Default settings. The webhook secret is generated once on first install.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'stall_name'                => 'Trạm La Cà',
			'stall_tagline'             => 'Gọi món nhanh tại quầy',
			'stall_description'         => 'Tìm món, thêm vào giỏ, nhập số điện thoại rồi quét VietQR để thanh toán.',
			'payment_prefix'            => 'ORDER',
			'theme_ink'                 => '#251914',
			'theme_paper'               => '#fff8e8',
			'theme_accent'              => '#d7652c',
			'bank_bin'                  => '970436',
			'bank_account_number'       => '1234567890',
			'bank_account_name'         => 'TRAM LA CA',
			'payment_timeout_seconds'   => 180,
			'kds_refresh_interval_ms'   => 2000,
			'order_status_refresh_interval_ms' => 7000,
			'menu_cache_ttl_seconds'    => 600,
			'max_open_orders'           => 0,
			'backlog_message'           => 'Quầy đang đông khách, vui lòng quay lại sau ít phút nhé.',
			'webhook_secret'            => wp_generate_password( 32, false, false ),
			'notification_provider'     => 'speedsms',
			'notification_api_endpoint' => 'https://api.speedsms.vn/index.php/sms/send',
			'notification_api_key'      => '',
			'notification_secret_key'   => '',
			'notification_template_id'  => 'replace-with-esms-or-zalo-template-id',
			'notification_sms_type'     => 2,
			'notification_sender'       => '',
			'notification_message_template' => '{stall_name} thông báo: Đơn hàng #{order_id} của bạn đã hoàn thành. Vui lòng đến quầy nhận món nhé!',
			'web_push_enabled'          => 0,
			'web_push_subject'          => 'mailto:owner@example.com',
			'web_push_public_key'       => '',
			'web_push_private_key'      => '',
			'promotion_rules'           => array(),
			'discount_quantity_enabled' => 0,
			'discount_quantity_min'     => 0,
			'discount_quantity_type'    => 'fixed',
			'discount_quantity_value'   => 0,
			'discount_quantity_label'   => 'Ưu đãi mua nhiều',
			'discount_total_enabled'    => 0,
			'discount_total_min'        => 0,
			'discount_total_type'       => 'fixed',
			'discount_total_value'      => 0,
			'discount_total_label'      => 'Ưu đãi đơn hàng',
			'bonus_enabled'             => 0,
			'bonus_min_qty'             => 0,
			'bonus_min_total'           => 0,
			'bonus_text'                => '',
		);
	}

	/**
	 * Initialize option values if they do not exist.
	 *
	 * @return void
	 */
	public static function maybe_initialize_settings() {
		$settings = get_option( self::OPTION_KEY );

		if ( ! is_array( $settings ) ) {
			add_option( self::OPTION_KEY, self::defaults() );
			return;
		}

		$defaults = self::defaults();
		$merged   = wp_parse_args( $settings, $defaults );

		if ( empty( $settings['webhook_secret'] ) ) {
			$merged['webhook_secret'] = $defaults['webhook_secret'];
		}

		if ( $merged !== $settings ) {
			update_option( self::OPTION_KEY, self::sanitize_settings( $merged ) );
		}
	}

	/**
	 * Get all plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		self::maybe_initialize_settings();

		$settings = get_option( self::OPTION_KEY, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	/**
	 * Get one setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public static function get( $key, $fallback = '' ) {
		$settings = self::get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Register the option with a sanitizer.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'laca_kds_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'type'              => 'array',
			)
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$existing = get_option( self::OPTION_KEY, array() );
		$existing = is_array( $existing ) ? $existing : array();
		$input    = is_array( $input ) ? $input : array();

		$webhook_secret = isset( $input['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $input['webhook_secret'] ) ) : '';
		if ( '' === $webhook_secret && ! empty( $existing['webhook_secret'] ) ) {
			$webhook_secret = sanitize_text_field( $existing['webhook_secret'] );
		}
		if ( '' === $webhook_secret ) {
			$webhook_secret = wp_generate_password( 32, false, false );
		}

		return array(
			'stall_name'                => isset( $input['stall_name'] ) ? sanitize_text_field( wp_unslash( $input['stall_name'] ) ) : '',
			'stall_tagline'             => isset( $input['stall_tagline'] ) ? sanitize_text_field( wp_unslash( $input['stall_tagline'] ) ) : '',
			'stall_description'         => isset( $input['stall_description'] ) ? sanitize_textarea_field( wp_unslash( $input['stall_description'] ) ) : '',
			'payment_prefix'            => self::sanitize_payment_prefix( isset( $input['payment_prefix'] ) ? wp_unslash( $input['payment_prefix'] ) : 'ORDER' ),
			'theme_ink'                 => self::sanitize_color( isset( $input['theme_ink'] ) ? $input['theme_ink'] : '', '#251914' ),
			'theme_paper'               => self::sanitize_color( isset( $input['theme_paper'] ) ? $input['theme_paper'] : '', '#fff8e8' ),
			'theme_accent'              => self::sanitize_color( isset( $input['theme_accent'] ) ? $input['theme_accent'] : '', '#d7652c' ),
			'bank_bin'                  => isset( $input['bank_bin'] ) ? sanitize_text_field( wp_unslash( $input['bank_bin'] ) ) : '',
			'bank_account_number'       => isset( $input['bank_account_number'] ) ? sanitize_text_field( wp_unslash( $input['bank_account_number'] ) ) : '',
			'bank_account_name'         => isset( $input['bank_account_name'] ) ? sanitize_text_field( wp_unslash( $input['bank_account_name'] ) ) : '',
			'payment_timeout_seconds'   => isset( $input['payment_timeout_seconds'] ) ? min( 3600, max( 0, absint( $input['payment_timeout_seconds'] ) ) ) : 180,
			'kds_refresh_interval_ms'   => isset( $input['kds_refresh_interval_ms'] ) ? min( 15000, max( 1000, absint( $input['kds_refresh_interval_ms'] ) ) ) : 2000,
			'order_status_refresh_interval_ms' => isset( $input['order_status_refresh_interval_ms'] ) ? min( 30000, max( 5000, absint( $input['order_status_refresh_interval_ms'] ) ) ) : 7000,
			'menu_cache_ttl_seconds'    => isset( $input['menu_cache_ttl_seconds'] ) ? min( DAY_IN_SECONDS, max( 0, absint( $input['menu_cache_ttl_seconds'] ) ) ) : 600,
			'max_open_orders'           => isset( $input['max_open_orders'] ) ? max( 0, absint( $input['max_open_orders'] ) ) : 0,
			'backlog_message'           => isset( $input['backlog_message'] ) ? sanitize_textarea_field( wp_unslash( $input['backlog_message'] ) ) : '',
			'webhook_secret'            => $webhook_secret,
			'notification_provider'     => self::sanitize_notification_provider( isset( $input['notification_provider'] ) ? wp_unslash( $input['notification_provider'] ) : 'speedsms' ),
			'notification_api_endpoint' => isset( $input['notification_api_endpoint'] ) ? esc_url_raw( wp_unslash( $input['notification_api_endpoint'] ) ) : '',
			'notification_api_key'      => isset( $input['notification_api_key'] ) ? sanitize_text_field( wp_unslash( $input['notification_api_key'] ) ) : '',
			'notification_secret_key'   => isset( $input['notification_secret_key'] ) ? sanitize_text_field( wp_unslash( $input['notification_secret_key'] ) ) : '',
			'notification_template_id'  => isset( $input['notification_template_id'] ) ? sanitize_text_field( wp_unslash( $input['notification_template_id'] ) ) : '',
			'notification_sms_type'     => isset( $input['notification_sms_type'] ) ? absint( $input['notification_sms_type'] ) : 2,
			'notification_sender'       => isset( $input['notification_sender'] ) ? sanitize_text_field( wp_unslash( $input['notification_sender'] ) ) : '',
			'notification_message_template' => isset( $input['notification_message_template'] ) ? sanitize_textarea_field( wp_unslash( $input['notification_message_template'] ) ) : self::defaults()['notification_message_template'],
			'web_push_enabled'          => ! empty( $input['web_push_enabled'] ) ? 1 : 0,
			'web_push_subject'          => isset( $input['web_push_subject'] ) ? sanitize_text_field( wp_unslash( $input['web_push_subject'] ) ) : 'mailto:owner@example.com',
			'web_push_public_key'       => isset( $input['web_push_public_key'] ) ? sanitize_text_field( wp_unslash( $input['web_push_public_key'] ) ) : '',
			'web_push_private_key'      => isset( $input['web_push_private_key'] ) ? sanitize_textarea_field( wp_unslash( $input['web_push_private_key'] ) ) : '',
			'promotion_rules'           => self::sanitize_promotion_rules( isset( $input['promotion_rules'] ) ? wp_unslash( $input['promotion_rules'] ) : array() ),
			'discount_quantity_enabled' => ! empty( $input['discount_quantity_enabled'] ) ? 1 : 0,
			'discount_quantity_min'     => isset( $input['discount_quantity_min'] ) ? max( 0, absint( $input['discount_quantity_min'] ) ) : 0,
			'discount_quantity_type'    => self::sanitize_discount_type( isset( $input['discount_quantity_type'] ) ? wp_unslash( $input['discount_quantity_type'] ) : 'fixed' ),
			'discount_quantity_value'   => isset( $input['discount_quantity_value'] ) ? max( 0, (float) sanitize_text_field( wp_unslash( $input['discount_quantity_value'] ) ) ) : 0,
			'discount_quantity_label'   => isset( $input['discount_quantity_label'] ) ? sanitize_text_field( wp_unslash( $input['discount_quantity_label'] ) ) : '',
			'discount_total_enabled'    => ! empty( $input['discount_total_enabled'] ) ? 1 : 0,
			'discount_total_min'        => isset( $input['discount_total_min'] ) ? max( 0, (float) sanitize_text_field( wp_unslash( $input['discount_total_min'] ) ) ) : 0,
			'discount_total_type'       => self::sanitize_discount_type( isset( $input['discount_total_type'] ) ? wp_unslash( $input['discount_total_type'] ) : 'fixed' ),
			'discount_total_value'      => isset( $input['discount_total_value'] ) ? max( 0, (float) sanitize_text_field( wp_unslash( $input['discount_total_value'] ) ) ) : 0,
			'discount_total_label'      => isset( $input['discount_total_label'] ) ? sanitize_text_field( wp_unslash( $input['discount_total_label'] ) ) : '',
			'bonus_enabled'             => ! empty( $input['bonus_enabled'] ) ? 1 : 0,
			'bonus_min_qty'             => isset( $input['bonus_min_qty'] ) ? max( 0, absint( $input['bonus_min_qty'] ) ) : 0,
			'bonus_min_total'           => isset( $input['bonus_min_total'] ) ? max( 0, (float) sanitize_text_field( wp_unslash( $input['bonus_min_total'] ) ) ) : 0,
			'bonus_text'                => isset( $input['bonus_text'] ) ? sanitize_textarea_field( wp_unslash( $input['bonus_text'] ) ) : '',
		);
	}

	/**
	 * Sanitize discount type.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	private static function sanitize_discount_type( $type ) {
		$type = sanitize_key( $type );

		return in_array( $type, array( 'fixed', 'percent' ), true ) ? $type : 'fixed';
	}

	/**
	 * Sanitize notification provider.
	 *
	 * @param string $provider Raw provider.
	 * @return string
	 */
	private static function sanitize_notification_provider( $provider ) {
		$provider = sanitize_key( $provider );

		return in_array( $provider, array( 'speedsms', 'esms', 'generic' ), true ) ? $provider : 'speedsms';
	}

	/**
	 * Sanitize multiple promotion rules.
	 *
	 * @param array $rules Raw promotion rules.
	 * @return array
	 */
	public static function sanitize_promotion_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$trigger_type = isset( $rule['trigger_type'] ) ? sanitize_key( $rule['trigger_type'] ) : 'quantity';
			$reward_type  = isset( $rule['reward_type'] ) ? sanitize_key( $rule['reward_type'] ) : 'gift';

			if ( ! in_array( $trigger_type, array( 'quantity', 'total' ), true ) ) {
				$trigger_type = 'quantity';
			}

			if ( ! in_array( $reward_type, array( 'gift', 'discount_fixed', 'discount_percent' ), true ) ) {
				$reward_type = 'gift';
			}

			$label          = isset( $rule['label'] ) ? sanitize_text_field( $rule['label'] ) : '';
			$gift_text      = isset( $rule['gift_text'] ) ? sanitize_text_field( $rule['gift_text'] ) : '';
			$gift_food_ids  = isset( $rule['gift_food_ids'] ) && is_array( $rule['gift_food_ids'] ) ? array_map( 'absint', $rule['gift_food_ids'] ) : array();
			$gift_food_ids  = array_values(
				array_filter(
					array_unique( $gift_food_ids ),
					static function ( $food_id ) {
						return $food_id && Laca_KDS_Food_CPT::POST_TYPE === get_post_type( $food_id );
					}
				)
			);
			$discount_value = isset( $rule['discount_value'] ) ? max( 0, (float) sanitize_text_field( $rule['discount_value'] ) ) : 0;
			$min_qty        = isset( $rule['min_qty'] ) ? max( 0, absint( $rule['min_qty'] ) ) : 0;
			$min_total      = isset( $rule['min_total'] ) ? max( 0, (float) sanitize_text_field( $rule['min_total'] ) ) : 0;
			$is_enabled     = ! empty( $rule['enabled'] ) ? 1 : 0;

			if ( '' === $label ) {
				$label = 'gift' === $reward_type ? __( 'Quà tặng tự động', 'laca-self-ordering-kds' ) : __( 'Ưu đãi tự động', 'laca-self-ordering-kds' );
			}

			$has_trigger = ( 'quantity' === $trigger_type && $min_qty > 0 ) || ( 'total' === $trigger_type && $min_total > 0 );
			$has_reward  = ( 'gift' === $reward_type && ( '' !== $gift_text || ! empty( $gift_food_ids ) ) ) || ( 'gift' !== $reward_type && $discount_value > 0 );

			if ( ! $has_trigger && ! $has_reward && ! $is_enabled ) {
				continue;
			}

			$sanitized[] = array(
				'enabled'        => $is_enabled,
				'label'          => $label,
				'trigger_type'   => $trigger_type,
				'min_qty'        => $min_qty,
				'min_total'      => $min_total,
				'reward_type'    => $reward_type,
				'discount_value' => $discount_value,
				'gift_text'      => $gift_text,
				'gift_food_ids'  => $gift_food_ids,
			);
		}

		return array_map( array( __CLASS__, 'hydrate_promotion_rule_gifts' ), array_values( $sanitized ) );
	}

	/**
	 * Add display data for gift choices.
	 *
	 * @param array $rule Promotion rule.
	 * @return array
	 */
	private static function hydrate_promotion_rule_gifts( $rule ) {
		$gift_food_ids = isset( $rule['gift_food_ids'] ) && is_array( $rule['gift_food_ids'] ) ? $rule['gift_food_ids'] : array();
		$gift_options  = array();

		foreach ( $gift_food_ids as $food_id ) {
			$food_id = absint( $food_id );
			if ( ! $food_id || Laca_KDS_Food_CPT::POST_TYPE !== get_post_type( $food_id ) || 'publish' !== get_post_status( $food_id ) ) {
				continue;
			}

			$gift_options[] = array(
				'id'    => $food_id,
				'name'  => get_the_title( $food_id ),
				'price' => Laca_KDS_Food_CPT::get_price( $food_id ),
			);
		}

		$rule['gift_food_ids'] = array_values( array_map( 'absint', $gift_food_ids ) );
		$rule['gift_options']  = $gift_options;

		return $rule;
	}

	/**
	 * Return current promotion rules, including legacy settings if present.
	 *
	 * @param array|null $settings Optional settings.
	 * @return array
	 */
	public static function get_promotion_rules( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : self::get_settings();
		$rules    = self::sanitize_promotion_rules( isset( $settings['promotion_rules'] ) ? $settings['promotion_rules'] : array() );

		if ( ! empty( $rules ) ) {
			return $rules;
		}

		$legacy_rules = array();
		if ( ! empty( $settings['discount_quantity_enabled'] ) && absint( $settings['discount_quantity_min'] ) > 0 && (float) $settings['discount_quantity_value'] > 0 ) {
			$legacy_rules[] = array(
				'enabled'        => 1,
				'label'          => sanitize_text_field( $settings['discount_quantity_label'] ),
				'trigger_type'   => 'quantity',
				'min_qty'        => absint( $settings['discount_quantity_min'] ),
				'min_total'      => 0,
				'reward_type'    => 'percent' === sanitize_key( $settings['discount_quantity_type'] ) ? 'discount_percent' : 'discount_fixed',
				'discount_value' => (float) $settings['discount_quantity_value'],
				'gift_text'      => '',
				'gift_food_ids'  => array(),
			);
		}

		if ( ! empty( $settings['discount_total_enabled'] ) && (float) $settings['discount_total_min'] > 0 && (float) $settings['discount_total_value'] > 0 ) {
			$legacy_rules[] = array(
				'enabled'        => 1,
				'label'          => sanitize_text_field( $settings['discount_total_label'] ),
				'trigger_type'   => 'total',
				'min_qty'        => 0,
				'min_total'      => (float) $settings['discount_total_min'],
				'reward_type'    => 'percent' === sanitize_key( $settings['discount_total_type'] ) ? 'discount_percent' : 'discount_fixed',
				'discount_value' => (float) $settings['discount_total_value'],
				'gift_text'      => '',
				'gift_food_ids'  => array(),
			);
		}

		if ( ! empty( $settings['bonus_enabled'] ) && ! empty( $settings['bonus_text'] ) ) {
			$legacy_rules[] = array(
				'enabled'        => 1,
				'label'          => __( 'Tặng kèm theo đơn', 'laca-self-ordering-kds' ),
				'trigger_type'   => absint( $settings['bonus_min_qty'] ) > 0 ? 'quantity' : 'total',
				'min_qty'        => absint( $settings['bonus_min_qty'] ),
				'min_total'      => (float) $settings['bonus_min_total'],
				'reward_type'    => 'gift',
				'discount_value' => 0,
				'gift_text'      => sanitize_text_field( $settings['bonus_text'] ),
				'gift_food_ids'  => array(),
			);
		}

		return $legacy_rules;
	}

	/**
	 * Sanitize the payment prefix used in VietQR content and SePay matching.
	 *
	 * @param string $prefix Raw prefix.
	 * @return string
	 */
	private static function sanitize_payment_prefix( $prefix ) {
		$prefix = strtoupper( sanitize_text_field( $prefix ) );
		$prefix = preg_replace( '/[^A-Z0-9]/', '', $prefix );
		$prefix = substr( $prefix, 0, 5 );

		return strlen( $prefix ) >= 2 ? $prefix : 'ORDER';
	}

	/**
	 * Sanitize a hex color.
	 *
	 * @param string $color Raw color.
	 * @param string $fallback Fallback color.
	 * @return string
	 */
	private static function sanitize_color( $color, $fallback ) {
		$sanitized = sanitize_hex_color( wp_unslash( $color ) );

		return $sanitized ? $sanitized : $fallback;
	}

	/**
	 * Register the settings submenu.
	 *
	 * @return void
	 */
	public static function register_settings_page() {
		add_submenu_page(
			Laca_KDS_Admin_KDS::MENU_SLUG,
			__( 'Laca Settings', 'laca-self-ordering-kds' ),
			__( 'Cài đặt', 'laca-self-ordering-kds' ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings UI focused on promotions.
	 *
	 * @return void
	 */
	public static function render_promotions_page() {
		self::render_settings_page( 'promo' );
	}

	/**
	 * Render one promotion rule row.
	 *
	 * @param array      $rule Rule data.
	 * @param int|string $index Row index.
	 * @return void
	 */
	private static function render_promotion_rule_row( $rule, $index ) {
		$defaults = array(
			'enabled'        => 0,
			'label'          => '',
			'trigger_type'   => 'quantity',
			'min_qty'        => 0,
			'min_total'      => 0,
			'reward_type'    => 'gift',
			'discount_value' => 0,
			'gift_text'      => '',
			'gift_food_ids'  => array(),
		);
		$rule     = wp_parse_args( is_array( $rule ) ? $rule : array(), $defaults );
		$number   = is_numeric( $index ) ? ( (int) $index + 1 ) : '__number__';
		$base     = self::OPTION_KEY . '[promotion_rules][' . $index . ']';
		$gift_food_options = self::get_gift_food_options();
		$selected_gifts    = isset( $rule['gift_food_ids'] ) && is_array( $rule['gift_food_ids'] ) ? array_map( 'absint', $rule['gift_food_ids'] ) : array();
		?>
		<div class="laca-promo-rule" data-rule-index="<?php echo esc_attr( $index ); ?>">
			<div class="laca-promo-rule__head">
				<strong><?php esc_html_e( 'Luật', 'laca-self-ordering-kds' ); ?> <span class="laca-promo-rule__number"><?php echo esc_html( $number ); ?></span></strong>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?> />
					<?php esc_html_e( 'Bật', 'laca-self-ordering-kds' ); ?>
				</label>
				<button type="button" class="button laca-remove-promo-rule"><?php esc_html_e( 'Xóa luật', 'laca-self-ordering-kds' ); ?></button>
			</div>
			<div class="laca-promo-rule__grid">
				<label>
					<?php esc_html_e( 'Tên luật', 'laca-self-ordering-kds' ); ?>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $rule['label'] ); ?>" placeholder="<?php esc_attr_e( 'VD: Mua 3 món tặng nước', 'laca-self-ordering-kds' ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'Điều kiện', 'laca-self-ordering-kds' ); ?>
					<select name="<?php echo esc_attr( $base ); ?>[trigger_type]">
						<option value="quantity" <?php selected( $rule['trigger_type'], 'quantity' ); ?>><?php esc_html_e( 'Theo số lượng món', 'laca-self-ordering-kds' ); ?></option>
						<option value="total" <?php selected( $rule['trigger_type'], 'total' ); ?>><?php esc_html_e( 'Theo tổng tiền', 'laca-self-ordering-kds' ); ?></option>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Từ số lượng', 'laca-self-ordering-kds' ); ?>
					<input type="number" min="0" name="<?php echo esc_attr( $base ); ?>[min_qty]" value="<?php echo esc_attr( $rule['min_qty'] ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'Hoặc đơn từ', 'laca-self-ordering-kds' ); ?>
					<input type="number" min="0" step="1000" name="<?php echo esc_attr( $base ); ?>[min_total]" value="<?php echo esc_attr( $rule['min_total'] ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'Ưu đãi', 'laca-self-ordering-kds' ); ?>
					<select name="<?php echo esc_attr( $base ); ?>[reward_type]">
						<option value="gift" <?php selected( $rule['reward_type'], 'gift' ); ?>><?php esc_html_e( 'Tặng kèm', 'laca-self-ordering-kds' ); ?></option>
						<option value="discount_fixed" <?php selected( $rule['reward_type'], 'discount_fixed' ); ?>><?php esc_html_e( 'Giảm tiền', 'laca-self-ordering-kds' ); ?></option>
						<option value="discount_percent" <?php selected( $rule['reward_type'], 'discount_percent' ); ?>><?php esc_html_e( 'Giảm %', 'laca-self-ordering-kds' ); ?></option>
					</select>
				</label>
				<label>
					<?php esc_html_e( 'Giá trị giảm', 'laca-self-ordering-kds' ); ?>
					<input type="number" min="0" step="any" name="<?php echo esc_attr( $base ); ?>[discount_value]" value="<?php echo esc_attr( $rule['discount_value'] ); ?>" placeholder="<?php esc_attr_e( 'VD: 10000 hoặc 10%', 'laca-self-ordering-kds' ); ?>" />
				</label>
				<label class="laca-promo-rule__wide">
					<?php esc_html_e( 'Nội dung quà tặng', 'laca-self-ordering-kds' ); ?>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[gift_text]" value="<?php echo esc_attr( $rule['gift_text'] ); ?>" placeholder="<?php esc_attr_e( 'VD: Tặng 1 nước mót / 1 trà đá', 'laca-self-ordering-kds' ); ?>" />
				</label>
				<label class="laca-promo-rule__wide">
					<?php esc_html_e( 'Món khách được chọn làm quà', 'laca-self-ordering-kds' ); ?>
					<select multiple name="<?php echo esc_attr( $base ); ?>[gift_food_ids][]" class="laca-promo-gift-foods">
						<?php foreach ( $gift_food_options as $food ) : ?>
							<option value="<?php echo esc_attr( $food['id'] ); ?>" <?php selected( in_array( absint( $food['id'] ), $selected_gifts, true ) ); ?>>
								<?php echo esc_html( $food['name'] . ' - ' . number_format_i18n( $food['price'] ) . 'đ' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="description"><?php esc_html_e( 'Giữ Cmd/Ctrl để chọn nhiều món. Khi đủ điều kiện, khách sẽ được chọn 1 món trong danh sách này.', 'laca-self-ordering-kds' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Return food options available for gift selection.
	 *
	 * @return array
	 */
	private static function get_gift_food_options() {
		$query = new WP_Query(
			array(
				'post_type'      => Laca_KDS_Food_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$foods = array();

		while ( $query->have_posts() ) {
			$query->the_post();
			$food_id = get_the_ID();
			$foods[] = array(
				'id'    => $food_id,
				'name'  => get_the_title(),
				'price' => Laca_KDS_Food_CPT::get_price( $food_id ),
			);
		}
		wp_reset_postdata();

		return $foods;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page( $default_section = 'brand' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'laca-self-ordering-kds' ) );
		}

		$allowed_sections = array( 'brand', 'bank', 'ops', 'combo', 'promo', 'sepay', 'sms', 'push', 'pages' );
		$active_section   = isset( $_GET['laca_section'] ) ? sanitize_key( wp_unslash( $_GET['laca_section'] ) ) : sanitize_key( $default_section );
		$active_section   = in_array( $active_section, $allowed_sections, true ) ? $active_section : 'brand';
		$settings       = self::get_settings();
		$menu_page_id   = self::get_menu_page_id();
		$menu_page_url  = self::get_menu_page_url();
		$menu_qr_url    = self::get_menu_page_qr_url();
		$status_page_url = self::get_status_page_url();
		$pickup_page_url = self::get_pickup_page_url();
		$edit_page_link = $menu_page_id ? get_edit_post_link( $menu_page_id ) : '';
		$promotion_rules = self::get_promotion_rules( $settings );
		$combo_list_url  = admin_url( 'edit.php?post_type=' . Laca_KDS_Combo_CPT::POST_TYPE );
		$test_message    = strtr(
			$settings['notification_message_template'],
			array(
				'{stall_name}'   => $settings['stall_name'],
				'{order_id}'     => '999',
				'{queue_number}' => '#999',
				'{phone}'        => '0901234567',
				'{total}'        => '50.000đ',
			)
		);
		?>
		<div class="wrap laca-kds-settings-wrap">
			<h1><?php esc_html_e( 'Cài đặt La Cà Self-Ordering KDS', 'laca-self-ordering-kds' ); ?></h1>
			<p><?php esc_html_e( 'Nhập thông tin vận hành tại đây. Không cần sửa wp-config.php hoặc code plugin.', 'laca-self-ordering-kds' ); ?></p>

			<div class="laca-settings-shell" data-active-section="<?php echo esc_attr( $active_section ); ?>">
			<nav class="laca-settings-hub" aria-label="<?php esc_attr_e( 'Điều hướng cài đặt', 'laca-self-ordering-kds' ); ?>">
				<div class="laca-settings-hub__group"><?php esc_html_e( 'Tổng quan / cấu hình chung', 'laca-self-ordering-kds' ); ?></div>
				<a href="#" data-section="brand" class="<?php echo 'brand' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '01', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'Thương hiệu', 'laca-self-ordering-kds' ); ?></a>
				<a href="#" data-section="bank" class="<?php echo 'bank' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '02', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'VietQR', 'laca-self-ordering-kds' ); ?></a>
				<a href="#" data-section="ops" class="<?php echo 'ops' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '03', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'Vận hành', 'laca-self-ordering-kds' ); ?></a>
				<div class="laca-settings-hub__group"><?php esc_html_e( 'Menu & bán hàng', 'laca-self-ordering-kds' ); ?></div>
				<a href="#" data-section="combo" class="<?php echo 'combo' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '04', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'Combo', 'laca-self-ordering-kds' ); ?></a>
				<a href="#" data-section="promo" class="<?php echo 'promo' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '05', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'Khuyến mãi', 'laca-self-ordering-kds' ); ?></a>
				<div class="laca-settings-hub__group"><?php esc_html_e( 'Tích hợp', 'laca-self-ordering-kds' ); ?></div>
				<a href="#" data-section="sepay" class="<?php echo 'sepay' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '06', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'SePay', 'laca-self-ordering-kds' ); ?></a>
				<a href="#" data-section="sms" class="<?php echo 'sms' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '07', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'SMS / Zalo', 'laca-self-ordering-kds' ); ?></a>
				<a href="#" data-section="push" class="<?php echo 'push' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '08', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'Web Push', 'laca-self-ordering-kds' ); ?></a>
				<div class="laca-settings-hub__group"><?php esc_html_e( 'Trang công khai', 'laca-self-ordering-kds' ); ?></div>
				<a href="#" data-section="pages" class="<?php echo 'pages' === $active_section ? 'is-active' : ''; ?>"><span><?php esc_html_e( '09', 'laca-self-ordering-kds' ); ?></span><?php esc_html_e( 'Trang menu', 'laca-self-ordering-kds' ); ?></a>
			</nav>

			<div class="laca-settings-grid">
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="laca-settings-card">
					<?php settings_fields( 'laca_kds_settings_group' ); ?>

					<section id="laca-settings-brand" class="laca-settings-section <?php echo 'brand' === $active_section ? 'is-active' : ''; ?>" data-section="brand">
					<h2><?php esc_html_e( 'Thương hiệu gian hàng', 'laca-self-ordering-kds' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="laca_stall_name"><?php esc_html_e( 'Tên gian hàng', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="laca_stall_name" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stall_name]" value="<?php echo esc_attr( $settings['stall_name'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_stall_tagline"><?php esc_html_e( 'Headline menu', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="large-text" id="laca_stall_tagline" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stall_tagline]" value="<?php echo esc_attr( $settings['stall_tagline'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_stall_description"><?php esc_html_e( 'Mô tả ngắn', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<textarea class="large-text" rows="3" id="laca_stall_description" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stall_description]"><?php echo esc_textarea( $settings['stall_description'] ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Màu chủ đề', 'laca-self-ordering-kds' ); ?></th>
							<td class="laca-color-fields">
								<label>
									<?php esc_html_e( 'Mực', 'laca-self-ordering-kds' ); ?>
									<input type="color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[theme_ink]" value="<?php echo esc_attr( $settings['theme_ink'] ); ?>" />
								</label>
								<label>
									<?php esc_html_e( 'Nền', 'laca-self-ordering-kds' ); ?>
									<input type="color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[theme_paper]" value="<?php echo esc_attr( $settings['theme_paper'] ); ?>" />
								</label>
								<label>
									<?php esc_html_e( 'Nhấn', 'laca-self-ordering-kds' ); ?>
									<input type="color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[theme_accent]" value="<?php echo esc_attr( $settings['theme_accent'] ); ?>" />
								</label>
							</td>
						</tr>
					</table>
					</section>

					<section id="laca-settings-bank" class="laca-settings-section <?php echo 'bank' === $active_section ? 'is-active' : ''; ?>" data-section="bank">
					<h2><?php esc_html_e( 'Thông tin ngân hàng VietQR', 'laca-self-ordering-kds' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="laca_payment_prefix"><?php esc_html_e( 'Prefix chuyển khoản', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="laca_payment_prefix" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[payment_prefix]" value="<?php echo esc_attr( laca_kds_get_payment_prefix() ); ?>" maxlength="5" pattern="[A-Za-z0-9]{2,5}" placeholder="9XQUA" />
								<p class="description">
									<?php esc_html_e( 'Khớp với ô Tiền tố trong SePay. Nên dùng 2-5 ký tự không dấu, ví dụ 9XQUA. Nội dung thanh toán mẫu:', 'laca-self-ordering-kds' ); ?>
									<code><?php echo esc_html( laca_kds_get_payment_prefix() . '0001' ); ?></code>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_bank_bin"><?php esc_html_e( 'Mã ngân hàng / BIN', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="laca_bank_bin" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bank_bin]" value="<?php echo esc_attr( $settings['bank_bin'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Ví dụ: 970436 cho Vietcombank. Dùng mã BIN theo chuẩn VietQR.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_bank_account_number"><?php esc_html_e( 'Số tài khoản', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="laca_bank_account_number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bank_account_number]" value="<?php echo esc_attr( $settings['bank_account_number'] ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_bank_account_name"><?php esc_html_e( 'Tên chủ tài khoản', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="laca_bank_account_name" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[bank_account_name]" value="<?php echo esc_attr( $settings['bank_account_name'] ); ?>" />
							</td>
						</tr>
					</table>
					</section>

					<section id="laca-settings-ops" class="laca-settings-section <?php echo 'ops' === $active_section ? 'is-active' : ''; ?>" data-section="ops">
					<h2><?php esc_html_e( 'Vận hành lúc cao điểm', 'laca-self-ordering-kds' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="laca_payment_timeout_seconds"><?php esc_html_e( 'Thời gian giữ QR', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="number" min="0" max="3600" class="small-text" id="laca_payment_timeout_seconds" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[payment_timeout_seconds]" value="<?php echo esc_attr( $settings['payment_timeout_seconds'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Tính bằng giây. Khi test nên dùng 600-900 giây để đủ thời gian mở app ngân hàng và xác nhận chuyển khoản. 0 = không tự hủy đơn pending.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_kds_refresh_interval_ms"><?php esc_html_e( 'Tốc độ cập nhật KDS', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="number" min="1000" max="15000" step="500" class="small-text" id="laca_kds_refresh_interval_ms" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[kds_refresh_interval_ms]" value="<?php echo esc_attr( $settings['kds_refresh_interval_ms'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Tính bằng mili-giây. Khuyến nghị 2000 để gần realtime mà vẫn nhẹ server.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_order_status_refresh_interval_ms"><?php esc_html_e( 'Tốc độ cập nhật trang khách', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="number" min="5000" max="30000" step="1000" class="small-text" id="laca_order_status_refresh_interval_ms" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[order_status_refresh_interval_ms]" value="<?php echo esc_attr( $settings['order_status_refresh_interval_ms'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Tính bằng mili-giây. Khuyến nghị 5000-10000 vì khách không cần realtime như màn hình bếp.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_menu_cache_ttl_seconds"><?php esc_html_e( 'Cache REST menu', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="number" min="0" max="<?php echo esc_attr( DAY_IN_SECONDS ); ?>" step="60" class="small-text" id="laca_menu_cache_ttl_seconds" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[menu_cache_ttl_seconds]" value="<?php echo esc_attr( $settings['menu_cache_ttl_seconds'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Tính bằng giây. Khuyến nghị 600. Cache tự đổi phiên bản khi sửa món, combo, danh mục hoặc trạng thái còn bán. 0 = tắt cache.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_max_open_orders"><?php esc_html_e( 'Giới hạn đơn đang chờ', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="number" min="0" class="small-text" id="laca_max_open_orders" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_open_orders]" value="<?php echo esc_attr( $settings['max_open_orders'] ); ?>" />
								<p class="description"><?php esc_html_e( '0 = không giới hạn. Nếu đạt giới hạn, khách tạm thời không tạo được đơn mới.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_backlog_message"><?php esc_html_e( 'Thông báo khi quá tải', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<textarea class="large-text" rows="3" id="laca_backlog_message" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[backlog_message]"><?php echo esc_textarea( $settings['backlog_message'] ); ?></textarea>
							</td>
						</tr>
					</table>
					</section>

					<section id="laca-settings-combo" class="laca-settings-section <?php echo 'combo' === $active_section ? 'is-active' : ''; ?>" data-section="combo">
					<h2><?php esc_html_e( 'Combo / Set món', 'laca-self-ordering-kds' ); ?></h2>
					<div class="laca-setup-guide">
						<h3><?php esc_html_e( 'Cách dùng combo', 'laca-self-ordering-kds' ); ?></h3>
						<p><?php esc_html_e( 'Combo là một mục riêng: vào Combo / Set món, chọn các món lẻ có sẵn, nhập giá combo. Menu khách sẽ hiển thị giá gốc cộng từ món lẻ và giá combo bạn nhập.', 'laca-self-ordering-kds' ); ?></p>
						<p>
							<a class="button" href="<?php echo esc_url( $combo_list_url ); ?>"><?php esc_html_e( 'Quản lý Combo / Set món', 'laca-self-ordering-kds' ); ?></a>
							<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Laca_KDS_Combo_CPT::POST_TYPE ) ); ?>"><?php esc_html_e( 'Tạo combo mới', 'laca-self-ordering-kds' ); ?></a>
						</p>
					</div>
					</section>

					<section id="laca-settings-promo" class="laca-settings-section <?php echo 'promo' === $active_section ? 'is-active' : ''; ?>" data-section="promo">
					<h2><?php esc_html_e( 'Khuyến mãi / tặng kèm', 'laca-self-ordering-kds' ); ?></h2>
					<div class="laca-setup-guide">
						<h3><?php esc_html_e( 'Luật khuyến mãi / tặng kèm', 'laca-self-ordering-kds' ); ?></h3>
						<p><?php esc_html_e( 'Có thể tạo nhiều luật cùng lúc, ví dụ: mua từ 3 món tặng 1 nước, mua từ 5 món tặng 1 nước khác, hoặc đơn từ 100.000đ giảm 10%. Các luật đủ điều kiện sẽ được áp dụng tự động.', 'laca-self-ordering-kds' ); ?></p>
					</div>
					<div class="laca-promo-rules" data-next-index="<?php echo esc_attr( count( $promotion_rules ) ); ?>">
						<?php foreach ( $promotion_rules as $index => $rule ) : ?>
							<?php self::render_promotion_rule_row( $rule, $index ); ?>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button button-primary laca-add-promo-rule"><?php esc_html_e( 'Thêm luật khuyến mãi', 'laca-self-ordering-kds' ); ?></button>
					<template id="laca-promo-rule-template">
						<?php self::render_promotion_rule_row( array(), '__index__' ); ?>
					</template>
					<p class="description"><?php esc_html_e( 'Lưu ý: nếu chọn "Theo số lượng món", plugin dùng ô Từ số lượng. Nếu chọn "Theo tổng tiền", plugin dùng ô Hoặc đơn từ.', 'laca-self-ordering-kds' ); ?></p>
					</section>

					<section id="laca-settings-sepay" class="laca-settings-section <?php echo 'sepay' === $active_section ? 'is-active' : ''; ?>" data-section="sepay">
					<h2><?php esc_html_e( 'Webhook xác nhận thanh toán', 'laca-self-ordering-kds' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="laca_webhook_secret"><?php esc_html_e( 'Webhook secret', 'laca-self-ordering-kds' ); ?></label>
							</th>
								<td>
									<input type="text" class="large-text code" id="laca_webhook_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[webhook_secret]" value="<?php echo esc_attr( $settings['webhook_secret'] ); ?>" />
									<p class="description">
										<?php esc_html_e( 'SePay gửi secret này qua header Authorization: Apikey WEBHOOK_SECRET. Plugin cũng hỗ trợ X-Laca-Webhook-Secret nếu dùng hệ thống trung gian.', 'laca-self-ordering-kds' ); ?>
									</p>
									<code><?php echo esc_html( rest_url( 'laca/v1/payment-webhook' ) ); ?></code>
								</td>
							</tr>
						</table>

						<div class="laca-setup-guide">
							<h3><?php esc_html_e( 'Hướng dẫn cấu hình SePay', 'laca-self-ordering-kds' ); ?></h3>
							<ol>
								<li><?php esc_html_e( 'Đăng nhập my.sepay.vn, kết nối tài khoản ngân hàng nhận tiền.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Vào Webhooks và tạo webhook mới.', 'laca-self-ordering-kds' ); ?></li>
								<li>
									<?php esc_html_e( 'Webhook URL:', 'laca-self-ordering-kds' ); ?>
									<code><?php echo esc_html( rest_url( 'laca/v1/payment-webhook' ) ); ?></code>
								</li>
								<li>
									<?php esc_html_e( 'Authentication/API Key:', 'laca-self-ordering-kds' ); ?>
									<code><?php echo esc_html( $settings['webhook_secret'] ); ?></code>
								</li>
								<li>
									<?php esc_html_e( 'Nếu SePay có bộ lọc nội dung, nhập prefix chuyển khoản:', 'laca-self-ordering-kds' ); ?>
									<code><?php echo esc_html( laca_kds_get_payment_prefix() ); ?></code>
								</li>
								<li>
									<?php esc_html_e( 'Trong phần nhận diện mã thanh toán của SePay, bật mẫu với Tiền tố như trên, Hậu tố từ 4 đến 10 ký tự và kiểu Số nguyên.', 'laca-self-ordering-kds' ); ?>
								</li>
								<li>
									<?php esc_html_e( 'Khi khách chuyển khoản đúng số tiền và nội dung dạng PREFIX + mã đơn 4 chữ số, ví dụ', 'laca-self-ordering-kds' ); ?>
									<code><?php echo esc_html( laca_kds_get_payment_prefix() . '0001' ); ?></code>,
									<?php esc_html_e( 'đơn sẽ tự chuyển từ pending sang paid.', 'laca-self-ordering-kds' ); ?>
								</li>
								<li><?php esc_html_e( 'Lưu ý: quét QR chưa phải là thanh toán. Khách phải bấm xác nhận/chuyển tiền thành công trong app ngân hàng thì SePay mới có giao dịch để gửi webhook.', 'laca-self-ordering-kds' ); ?></li>
							</ol>
							<p>
								<?php esc_html_e( 'Webhook thành công phải trả về JSON:', 'laca-self-ordering-kds' ); ?>
								<code>{"success":true}</code>
							</p>
						</div>
					</section>

						<section id="laca-settings-sms" class="laca-settings-section <?php echo 'sms' === $active_section ? 'is-active' : ''; ?>" data-section="sms">
						<h2><?php esc_html_e( 'SMS / Zalo ZNS', 'laca-self-ordering-kds' ); ?></h2>
						<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="laca_notification_provider"><?php esc_html_e( 'Nhà cung cấp', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<select id="laca_notification_provider" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_provider]">
									<option value="speedsms" <?php selected( $settings['notification_provider'], 'speedsms' ); ?>>SpeedSMS</option>
									<option value="esms" <?php selected( $settings['notification_provider'], 'esms' ); ?>>eSMS.vn</option>
									<option value="generic" <?php selected( $settings['notification_provider'], 'generic' ); ?>>Generic API</option>
								</select>
								<p class="description"><?php esc_html_e( 'Nếu SpeedSMS bị kẹt sender_not_found, bạn có thể đổi sang eSMS.vn để test nhanh bằng ApiKey + SecretKey.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_notification_api_endpoint"><?php esc_html_e( 'API endpoint', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="url" class="large-text" id="laca_notification_api_endpoint" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_api_endpoint]" value="<?php echo esc_url( $settings['notification_api_endpoint'] ); ?>" placeholder="https://api.example.com/zalo-zns-or-esms/send" />
								<p class="description"><?php esc_html_e( 'Khi chọn eSMS.vn, plugin tự dùng endpoint POST JSON khuyến nghị: https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post_json/', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_notification_api_key"><?php esc_html_e( 'API key / token', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="password" class="large-text" id="laca_notification_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_api_key]" value="<?php echo esc_attr( $settings['notification_api_key'] ); ?>" autocomplete="new-password" />
								<p class="description"><?php esc_html_e( 'SpeedSMS: API token. eSMS: ApiKey.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_notification_secret_key"><?php esc_html_e( 'Secret Key', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="password" class="large-text" id="laca_notification_secret_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_secret_key]" value="<?php echo esc_attr( $settings['notification_secret_key'] ); ?>" autocomplete="new-password" />
								<p class="description"><?php esc_html_e( 'Chỉ dùng cho eSMS.vn. SpeedSMS có thể để trống.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_notification_template_id"><?php esc_html_e( 'Template ID', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="laca_notification_template_id" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_template_id]" value="<?php echo esc_attr( $settings['notification_template_id'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Dùng cho Zalo ZNS/eSMS nếu nhà cung cấp yêu cầu template.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_notification_sms_type"><?php esc_html_e( 'SMS type', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="number" class="small-text" min="1" max="30" id="laca_notification_sms_type" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_sms_type]" value="<?php echo esc_attr( $settings['notification_sms_type'] ); ?>" />
								<p class="description"><?php esc_html_e( 'SpeedSMS: 2/3/4 tùy dịch vụ. eSMS: thường dùng 2 cho Brandname hoặc 8 cho đầu số cố định/template giá rẻ.', 'laca-self-ordering-kds' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="laca_notification_sender"><?php esc_html_e( 'Sender / Brandname', 'laca-self-ordering-kds' ); ?></label>
							</th>
							<td>
								<input type="text" class="regular-text" id="laca_notification_sender" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_sender]" value="<?php echo esc_attr( $settings['notification_sender'] ); ?>" />
								<p class="description"><?php esc_html_e( 'SpeedSMS: Notify/Verify hoặc Brandname đã bật. eSMS: nhập Brandname đã đăng ký nếu SmsType yêu cầu.', 'laca-self-ordering-kds' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="laca_notification_message_template"><?php esc_html_e( 'Tin nhắn mẫu', 'laca-self-ordering-kds' ); ?></label>
								</th>
								<td>
									<textarea class="large-text" rows="3" id="laca_notification_message_template" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notification_message_template]"><?php echo esc_textarea( $settings['notification_message_template'] ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'Biến có thể dùng:', 'laca-self-ordering-kds' ); ?>
										<code>{stall_name}</code>
										<code>{order_id}</code>
										<code>{queue_number}</code>
										<code>{phone}</code>
										<code>{total}</code>
									</p>
								</td>
							</tr>
						</table>

						<div class="laca-setup-guide">
							<h3><?php esc_html_e( 'Hướng dẫn cấu hình SpeedSMS', 'laca-self-ordering-kds' ); ?></h3>
							<ol>
								<li><?php esc_html_e( 'Đăng nhập connect.speedsms.vn và lấy API access token trong phần tài khoản/API.', 'laca-self-ordering-kds' ); ?></li>
								<li>
									<?php esc_html_e( 'API Endpoint nên dùng:', 'laca-self-ordering-kds' ); ?>
									<code>https://api.speedsms.vn/index.php/sms/send</code>
								</li>
								<li><?php esc_html_e( 'Dán API access token vào ô API key / token.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Để test nhanh theo tài liệu SpeedSMS, thử SMS type = 2 và để trống sender. Plugin gửi sender là chuỗi rỗng.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Nếu type 2 không được tài khoản hỗ trợ, thử SMS type = 4 với sender Notify. Nếu vẫn sender_not_found, thử sender Verify.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Nếu dùng Brandname, đăng ký Brandname với SpeedSMS trước, đặt SMS type = 3 và nhập sender đúng Brandname đã duyệt.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Không cần cấu hình webhook SpeedSMS để gửi SMS hoàn đơn. Webhook SpeedSMS chỉ báo trạng thái delivered/incoming sau khi tin đã gửi thành công.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'SMS chỉ được gửi khi admin bấm Hoàn thành trong KDS Manager.', 'laca-self-ordering-kds' ); ?></li>
							</ol>
							<p>
								<?php esc_html_e( 'Tin nhắn mẫu:', 'laca-self-ordering-kds' ); ?>
								<code><?php echo esc_html( strtr( $settings['notification_message_template'], array( '{stall_name}' => $settings['stall_name'], '{order_id}' => '123', '{queue_number}' => '#123', '{phone}' => '0901234567', '{total}' => '50.000đ' ) ) ); ?></code>
							</p>
						</div>

						<div class="laca-setup-guide">
							<h3><?php esc_html_e( 'Gợi ý thay SpeedSMS bằng eSMS.vn', 'laca-self-ordering-kds' ); ?></h3>
							<ol>
								<li><?php esc_html_e( 'Chọn Nhà cung cấp = eSMS.vn.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'API endpoint có thể để nguyên; plugin tự dùng endpoint eSMS POST JSON khuyến nghị khi chọn provider eSMS.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Nhập ApiKey vào ô API key / token và SecretKey vào ô Secret Key.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Nếu dùng Brandname, nhập Brandname vào Sender / Brandname và đặt SMS type theo dịch vụ eSMS đã bật. Thường type 2 = Brandname, type 8 = đầu số cố định/template giá rẻ.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Bấm Lưu thay đổi, sau đó dùng nút Gửi SMS test.', 'laca-self-ordering-kds' ); ?></li>
							</ol>
						</div>

						<div class="laca-setup-guide laca-sms-test-box">
							<h3><?php esc_html_e( 'Test gửi SMS', 'laca-self-ordering-kds' ); ?></h3>
							<p><?php esc_html_e( 'Bấm Lưu thay đổi trước khi test để plugin dùng đúng API key, SMS type và sender mới nhất.', 'laca-self-ordering-kds' ); ?></p>
							<div class="laca-sms-test-form">
								<label for="laca_sms_test_phone"><?php esc_html_e( 'Số điện thoại nhận test', 'laca-self-ordering-kds' ); ?></label>
								<input type="tel" id="laca_sms_test_phone" class="regular-text" placeholder="0901234567" />
								<label for="laca_sms_test_message"><?php esc_html_e( 'Nội dung test', 'laca-self-ordering-kds' ); ?></label>
								<textarea id="laca_sms_test_message" class="large-text" rows="3"><?php echo esc_textarea( $test_message ); ?></textarea>
								<button type="button" class="button button-primary laca-test-sms-button"><?php esc_html_e( 'Gửi SMS test', 'laca-self-ordering-kds' ); ?></button>
								<span class="spinner laca-test-sms-spinner"></span>
								<div class="laca-test-sms-result" aria-live="polite"></div>
							</div>
							<p class="description"><?php esc_html_e( 'Nếu trả sender_not_found: sender hiện tại chưa được bật cho token này. Thử type 2 sender rỗng, hoặc type 4 với Notify/Verify. Nếu cả hai đều lỗi, cần SpeedSMS bật quyền sender/dịch vụ cho tài khoản.', 'laca-self-ordering-kds' ); ?></p>
						</div>
						</section>

						<section id="laca-settings-push" class="laca-settings-section <?php echo 'push' === $active_section ? 'is-active' : ''; ?>" data-section="push">
						<h2><?php esc_html_e( 'Browser Notification / Web Push PWA', 'laca-self-ordering-kds' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Bật Web Push', 'laca-self-ordering-kds' ); ?></th>
								<td>
									<label class="laca-food-inline-switch">
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[web_push_enabled]" value="1" <?php checked( ! empty( $settings['web_push_enabled'] ) ); ?> />
										<span><?php esc_html_e( 'Gửi Web Push song song với SMS khi đơn đã thanh toán được hoàn thành', 'laca-self-ordering-kds' ); ?></span>
									</label>
									<p class="description"><?php esc_html_e( 'Browser Notification khi khách đang mở trang vẫn hoạt động bằng realtime polling. Web Push cần HTTPS, service worker và VAPID key để có thể báo tốt hơn trên điện thoại.', 'laca-self-ordering-kds' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="laca_web_push_subject"><?php esc_html_e( 'VAPID subject', 'laca-self-ordering-kds' ); ?></label>
								</th>
								<td>
									<input type="text" class="large-text" id="laca_web_push_subject" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[web_push_subject]" value="<?php echo esc_attr( $settings['web_push_subject'] ); ?>" placeholder="mailto:you@example.com" />
									<p class="description"><?php esc_html_e( 'Nên dùng email liên hệ dạng mailto:you@example.com hoặc URL website.', 'laca-self-ordering-kds' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="laca_web_push_public_key"><?php esc_html_e( 'VAPID public key', 'laca-self-ordering-kds' ); ?></label>
								</th>
								<td>
									<textarea class="large-text code" rows="2" id="laca_web_push_public_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[web_push_public_key]"><?php echo esc_textarea( $settings['web_push_public_key'] ); ?></textarea>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="laca_web_push_private_key"><?php esc_html_e( 'VAPID private key', 'laca-self-ordering-kds' ); ?></label>
								</th>
								<td>
									<textarea class="large-text code" rows="5" id="laca_web_push_private_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[web_push_private_key]"><?php echo esc_textarea( $settings['web_push_private_key'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Có thể dán privateKey base64url từ web-push CLI hoặc EC P-256 private key dạng PEM. Giữ bí mật key này, không chia sẻ công khai.', 'laca-self-ordering-kds' ); ?></p>
								</td>
							</tr>
						</table>
						<div class="laca-setup-guide">
							<h3><?php esc_html_e( 'Cách tạo VAPID key', 'laca-self-ordering-kds' ); ?></h3>
							<ol>
								<li><?php esc_html_e( 'Cài Node.js trên máy setup, chạy: npm install -g web-push', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Chạy: web-push generate-vapid-keys --json', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Dán publicKey vào ô VAPID public key.', 'laca-self-ordering-kds' ); ?></li>
								<li><?php esc_html_e( 'Dán privateKey vào ô VAPID private key. Plugin hỗ trợ privateKey base64url của web-push CLI hoặc PEM EC P-256.', 'laca-self-ordering-kds' ); ?></li>
							</ol>
							<p><?php esc_html_e( 'Lưu ý iPhone: Web Push ổn nhất khi khách dùng Safari và thêm website vào Home Screen như PWA. Nếu không, SMS/Zalo vẫn là kênh dự phòng quan trọng.', 'laca-self-ordering-kds' ); ?></p>
						</div>
						</section>

						<div class="laca-settings-submit">
							<?php submit_button( __( 'Lưu cài đặt', 'laca-self-ordering-kds' ) ); ?>
						</div>
					</form>

				<div id="laca-settings-pages" class="laca-settings-card laca-settings-card--qr laca-settings-section <?php echo 'pages' === $active_section ? 'is-active' : ''; ?>" data-section="pages">
					<h2><?php esc_html_e( 'Trang menu & QR cho khách', 'laca-self-ordering-kds' ); ?></h2>
					<?php if ( $menu_page_url ) : ?>
						<p><?php esc_html_e( 'Plugin đã tự tạo trang menu. In mã QR này để khách quét và gọi món.', 'laca-self-ordering-kds' ); ?></p>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( $menu_page_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Mở trang menu', 'laca-self-ordering-kds' ); ?>
							</a>
							<?php if ( $edit_page_link ) : ?>
								<a class="button" href="<?php echo esc_url( $edit_page_link ); ?>">
									<?php esc_html_e( 'Sửa page', 'laca-self-ordering-kds' ); ?>
								</a>
							<?php endif; ?>
						</p>
						<div class="laca-menu-qr-frame">
							<img src="<?php echo esc_url( $menu_qr_url ); ?>" alt="<?php esc_attr_e( 'QR trang menu đặt món', 'laca-self-ordering-kds' ); ?>" />
						</div>
						<p><code><?php echo esc_html( $menu_page_url ); ?></code></p>
						<?php if ( $status_page_url ) : ?>
							<p>
								<strong><?php esc_html_e( 'Trang theo dõi đơn:', 'laca-self-ordering-kds' ); ?></strong><br />
								<code><?php echo esc_html( $status_page_url ); ?></code>
							</p>
						<?php endif; ?>
						<?php if ( $pickup_page_url ) : ?>
							<p>
								<strong><?php esc_html_e( 'Màn hình gọi món:', 'laca-self-ordering-kds' ); ?></strong><br />
								<a class="button" href="<?php echo esc_url( $pickup_page_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Mở màn hình pickup', 'laca-self-ordering-kds' ); ?></a>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Chưa có trang menu tự động.', 'laca-self-ordering-kds' ); ?></p>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'laca_kds_sync_menu_page' ); ?>
						<input type="hidden" name="action" value="laca_kds_sync_menu_page" />
						<?php submit_button( __( 'Tạo / đồng bộ lại trang menu', 'laca-self-ordering-kds' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle manual page sync from admin UI.
	 *
	 * @return void
	 */
	public static function handle_sync_menu_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'laca-self-ordering-kds' ) );
		}

		check_admin_referer( 'laca_kds_sync_menu_page' );

		self::maybe_create_public_pages( true );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SETTINGS_PAGE_SLUG,
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Send a test SMS/ZNS request from the settings page.
	 *
	 * @return void
	 */
	public static function ajax_test_notification() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Bạn không có quyền test SMS.', 'laca-self-ordering-kds' ) ),
				403
			);
		}

		check_ajax_referer( 'laca_kds_settings_nonce', 'nonce' );

		$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$message  = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$settings = self::get_settings();

		$test_settings = array(
			'notification_provider'     => isset( $_POST['provider'] ) ? self::sanitize_notification_provider( wp_unslash( $_POST['provider'] ) ) : $settings['notification_provider'],
			'notification_api_endpoint' => isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : $settings['notification_api_endpoint'],
			'notification_api_key'      => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : $settings['notification_api_key'],
			'notification_secret_key'   => isset( $_POST['secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) : $settings['notification_secret_key'],
			'notification_template_id'  => isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : $settings['notification_template_id'],
			'notification_sms_type'     => isset( $_POST['sms_type'] ) ? absint( $_POST['sms_type'] ) : absint( $settings['notification_sms_type'] ),
			'notification_sender'       => isset( $_POST['sender'] ) ? sanitize_text_field( wp_unslash( $_POST['sender'] ) ) : $settings['notification_sender'],
		);

		if ( '' === trim( $phone ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Vui lòng nhập số điện thoại test.', 'laca-self-ordering-kds' ) ),
				400
			);
		}

		if ( '' === trim( $message ) ) {
			$message = __( 'Laca KDS test SMS.', 'laca-self-ordering-kds' );
		}

		$response = laca_kds_send_notification_request( $phone, $message, 0, array( 'id' => 0, 'customer_phone' => $phone ), $test_settings );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array( 'message' => $response->get_error_message() ),
				500
			);
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = (string) wp_remote_retrieve_body( $response );
		$decoded   = json_decode( $body, true );
		$status    = is_array( $decoded ) && isset( $decoded['status'] ) ? strtolower( (string) $decoded['status'] ) : '';
		$code      = '';
		if ( is_array( $decoded ) && isset( $decoded['CodeResponse'] ) ) {
			$code = (string) $decoded['CodeResponse'];
		} elseif ( is_array( $decoded ) && isset( $decoded['CodeResult'] ) ) {
			$code = (string) $decoded['CodeResult'];
		} elseif ( is_array( $decoded ) && isset( $decoded['code'] ) ) {
			$code = (string) $decoded['code'];
		}
		$success   = $http_code >= 200 && $http_code < 300 && in_array( $status, array( 'success', 'queued', 'sent', 'ok' ), true );
		$success   = $success || ( $http_code >= 200 && $http_code < 300 && in_array( $code, array( '0', '00', '100' ), true ) );

		if ( $success ) {
			wp_send_json_success(
				array(
					'message'   => __( 'Nhà cung cấp đã nhận request test thành công.', 'laca-self-ordering-kds' ),
					'http_code' => $http_code,
					'body'      => $body,
				)
			);
		}

		wp_send_json_error(
			array(
				'message'   => sprintf(
					/* translators: %s: provider response body. */
					__( 'Provider trả lỗi: %s', 'laca-self-ordering-kds' ),
					$body ? $body : __( 'Không có response body.', 'laca-self-ordering-kds' )
				),
				'http_code' => $http_code,
				'body'      => $body,
			),
			200
		);
	}

	/**
	 * Add the plugin template to the Page Template selector.
	 *
	 * @param array $templates Available templates.
	 * @return array
	 */
	public static function register_page_template( $templates ) {
		$templates[ self::MENU_PAGE_TEMPLATE ] = __( 'Laca Menu App', 'laca-self-ordering-kds' );
		$templates[ self::STATUS_PAGE_TEMPLATE ] = __( 'Laca Order Status', 'laca-self-ordering-kds' );
		$templates[ self::PICKUP_PAGE_TEMPLATE ] = __( 'Laca Pickup Screen', 'laca-self-ordering-kds' );

		return $templates;
	}

	/**
	 * Load the plugin page template for pages using the Laca template.
	 *
	 * @param string $template Current resolved template.
	 * @return string
	 */
	public static function load_page_template( $template ) {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}

		$page_template = get_page_template_slug( get_queried_object_id() );
		$templates     = array(
			self::MENU_PAGE_TEMPLATE,
			self::STATUS_PAGE_TEMPLATE,
			self::PICKUP_PAGE_TEMPLATE,
		);

		if ( ! in_array( $page_template, $templates, true ) ) {
			return $template;
		}

		$plugin_template = LACA_KDS_PLUGIN_DIR . 'templates/' . $page_template;

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}

	/**
	 * Create or repair all public plugin pages.
	 *
	 * @param bool $force_repair Whether to update status/template on existing pages.
	 * @return void
	 */
	public static function maybe_create_public_pages( $force_repair = false ) {
		self::maybe_create_menu_page( $force_repair );
		self::maybe_create_status_page( $force_repair );
		self::maybe_create_pickup_page( $force_repair );
	}

	/**
	 * Create or repair the public menu page.
	 *
	 * @param bool $force_repair Whether to update status/template on existing page.
	 * @return int Page ID.
	 */
	public static function maybe_create_menu_page( $force_repair = false ) {
		return self::create_or_repair_public_page(
			self::MENU_PAGE_OPTION,
			'menu',
			self::MENU_PAGE_TEMPLATE,
			__( 'Menu La Cà', 'laca-self-ordering-kds' ),
			'laca-menu',
			$force_repair
		);
	}

	/**
	 * Create or repair the public order status page.
	 *
	 * @param bool $force_repair Whether to update status/template on existing page.
	 * @return int Page ID.
	 */
	public static function maybe_create_status_page( $force_repair = false ) {
		return self::create_or_repair_public_page(
			self::STATUS_PAGE_OPTION,
			'status',
			self::STATUS_PAGE_TEMPLATE,
			__( 'Theo dõi đơn hàng', 'laca-self-ordering-kds' ),
			'laca-order-status',
			$force_repair
		);
	}

	/**
	 * Create or repair the public pickup screen page.
	 *
	 * @param bool $force_repair Whether to update status/template on existing page.
	 * @return int Page ID.
	 */
	public static function maybe_create_pickup_page( $force_repair = false ) {
		return self::create_or_repair_public_page(
			self::PICKUP_PAGE_OPTION,
			'pickup',
			self::PICKUP_PAGE_TEMPLATE,
			__( 'Đơn đã xong', 'laca-self-ordering-kds' ),
			'laca-pickup-screen',
			$force_repair
		);
	}

	/**
	 * Create or repair one public utility page.
	 *
	 * @param string $option_key Option key storing page ID.
	 * @param string $role Page role.
	 * @param string $template Page template.
	 * @param string $title Page title.
	 * @param string $slug Page slug.
	 * @param bool   $force_repair Whether to update existing page.
	 * @return int
	 */
	private static function create_or_repair_public_page( $option_key, $role, $template, $title, $slug, $force_repair = false ) {
		$page_id = self::get_public_page_id( $option_key );

		if ( ! $page_id ) {
			$existing = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::PUBLIC_PAGE_ROLE_META,
					'meta_value'     => $role,
				)
			);

			if ( ! empty( $existing ) ) {
				$page_id = absint( $existing[0] );
				update_option( $option_key, $page_id, false );
			}
		}

		if ( ! $page_id && 'menu' === $role ) {
			$legacy = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::MENU_PAGE_META_KEY,
					'meta_value'     => '1',
				)
			);

			if ( ! empty( $legacy ) ) {
				$page_id = absint( $legacy[0] );
				update_option( $option_key, $page_id, false );
			}
		}

		if ( ! $page_id ) {
			$page_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '',
				),
				true
			);

			if ( is_wp_error( $page_id ) ) {
				return 0;
			}

			$page_id = absint( $page_id );
			update_option( $option_key, $page_id, false );
		}

		if ( $page_id && $force_repair ) {
			wp_update_post(
				array(
					'ID'          => $page_id,
					'post_status' => 'publish',
				)
			);
		}

		if ( $page_id ) {
			update_post_meta( $page_id, '_wp_page_template', $template );
			update_post_meta( $page_id, self::PUBLIC_PAGE_ROLE_META, $role );

			if ( 'menu' === $role ) {
				update_post_meta( $page_id, self::MENU_PAGE_META_KEY, '1' );
			}
		}

		return absint( $page_id );
	}

	/**
	 * Get one stored public page ID if it still exists.
	 *
	 * @param string $option_key Option key.
	 * @return int
	 */
	private static function get_public_page_id( $option_key ) {
		$page_id = absint( get_option( $option_key ) );

		if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
			return $page_id;
		}

		return 0;
	}

	/**
	 * Get the stored menu page ID if it still exists.
	 *
	 * @return int
	 */
	public static function get_menu_page_id() {
		return self::get_public_page_id( self::MENU_PAGE_OPTION );
	}

	/**
	 * Get the menu page permalink.
	 *
	 * @return string
	 */
	public static function get_menu_page_url() {
		$page_id = self::get_menu_page_id();

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return $permalink ? $permalink : add_query_arg( 'page_id', $page_id, home_url( '/' ) );
	}

	/**
	 * Get the order status page URL.
	 *
	 * @return string
	 */
	public static function get_status_page_url() {
		return self::get_public_page_url( self::STATUS_PAGE_OPTION );
	}

	/**
	 * Get the pickup display page URL.
	 *
	 * @return string
	 */
	public static function get_pickup_page_url() {
		return self::get_public_page_url( self::PICKUP_PAGE_OPTION );
	}

	/**
	 * Get one public page permalink.
	 *
	 * @param string $option_key Option key.
	 * @return string
	 */
	private static function get_public_page_url( $option_key ) {
		$page_id = self::get_public_page_id( $option_key );

		if ( ! $page_id ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		return $permalink ? $permalink : add_query_arg( 'page_id', $page_id, home_url( '/' ) );
	}

	/**
	 * Get an automatically generated QR image URL for the menu page.
	 *
	 * @return string
	 */
	public static function get_menu_page_qr_url() {
		$menu_page_url = self::get_menu_page_url();

		if ( ! $menu_page_url ) {
			return '';
		}

		$qr_url = add_query_arg(
			array(
				'text'   => $menu_page_url,
				'size'   => 420,
				'margin' => 2,
			),
			'https://quickchart.io/qr'
		);

		return esc_url_raw( $qr_url );
	}
}
