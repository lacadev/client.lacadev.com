<?php
/**
 * Plugin Name: Laca Self-Ordering KDS
 * Description: Lightweight self-ordering and kitchen display plugin for food festival stalls. Built with CPT, a custom order table, AJAX, and REST API webhooks.
 * Version: 1.0.33
 * Author: Laca
 * Text Domain: laca-self-ordering-kds
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LACA_KDS_VERSION', '1.0.33' );
define( 'LACA_KDS_PLUGIN_FILE', __FILE__ );
define( 'LACA_KDS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LACA_KDS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LACA_KDS_PLUGIN_DIR . 'includes/class-laca-orders-repository.php';
require_once LACA_KDS_PLUGIN_DIR . 'includes/class-laca-food-cpt.php';
require_once LACA_KDS_PLUGIN_DIR . 'includes/class-laca-admin-kds.php';
require_once LACA_KDS_PLUGIN_DIR . 'includes/class-laca-combo-cpt.php';
require_once LACA_KDS_PLUGIN_DIR . 'includes/class-laca-settings.php';
require_once LACA_KDS_PLUGIN_DIR . 'includes/class-laca-frontend.php';
require_once LACA_KDS_PLUGIN_DIR . 'includes/class-laca-rest-api.php';

/**
 * Plugin activation callback.
 */
function laca_kds_activate() {
	Laca_KDS_Orders_Repository::create_table();
	Laca_KDS_Settings::maybe_initialize_settings();
	Laca_KDS_Food_CPT::register_taxonomy();
	Laca_KDS_Food_CPT::register_post_type();
	Laca_KDS_Combo_CPT::register_post_type();
	Laca_KDS_Food_CPT::maybe_seed_default_terms();
	Laca_KDS_Settings::schedule_menu_page_creation();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'laca_kds_activate' );

/**
 * Plugin deactivation callback.
 */
function laca_kds_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'laca_kds_deactivate' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function laca_kds_bootstrap() {
	Laca_KDS_Orders_Repository::maybe_upgrade_schema();
	Laca_KDS_Food_CPT::init();
	Laca_KDS_Admin_KDS::init();
	Laca_KDS_Combo_CPT::init();
	Laca_KDS_Settings::init();
	Laca_KDS_Frontend::init();
	Laca_KDS_REST_API::init();
}
add_action( 'plugins_loaded', 'laca_kds_bootstrap' );

/**
 * Return the custom orders table name.
 *
 * @return string
 */
function laca_kds_orders_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'laca_orders';
}

/**
 * Return the custom payment logs table name.
 *
 * @return string
 */
function laca_kds_payment_logs_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'laca_payment_logs';
}

/**
 * Return the custom notification logs table name.
 *
 * @return string
 */
function laca_kds_notification_logs_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'laca_notification_logs';
}

/**
 * Return the custom web push subscriptions table name.
 *
 * @return string
 */
function laca_kds_push_subscriptions_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'laca_push_subscriptions';
}

/**
 * Bank details used by VietQR.
 *
 * @return array
 */
function laca_kds_get_bank_details() {
	$defaults = array(
		'bank_bin'       => Laca_KDS_Settings::get( 'bank_bin' ),
		'account_number' => Laca_KDS_Settings::get( 'bank_account_number' ),
		'account_name'   => Laca_KDS_Settings::get( 'bank_account_name' ),
	);

	$details = apply_filters( 'laca_kds_bank_details', $defaults );
	$details = is_array( $details ) ? wp_parse_args( $details, $defaults ) : $defaults;

	return array(
		'bank_bin'       => sanitize_text_field( $details['bank_bin'] ),
		'account_number' => sanitize_text_field( $details['account_number'] ),
		'account_name'   => sanitize_text_field( $details['account_name'] ),
	);
}

/**
 * Payment prefix configured in the admin UI.
 *
 * SePay's payment-code recognizer expects a short alphanumeric prefix, usually
 * 2-5 characters, followed by a numeric suffix. Keep this helper centralized so
 * the VietQR content and webhook matcher never drift apart.
 *
 * @return string
 */
function laca_kds_get_payment_prefix() {
	$prefix = strtoupper( sanitize_text_field( Laca_KDS_Settings::get( 'payment_prefix', 'ORDER' ) ) );
	$prefix = preg_replace( '/[^A-Z0-9]/', '', $prefix );
	$prefix = substr( $prefix, 0, 5 );

	return strlen( $prefix ) >= 2 ? $prefix : 'ORDER';
}

/**
 * Numeric payment suffix used after the prefix.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function laca_kds_get_payment_order_code( $order_id ) {
	return str_pad( (string) absint( $order_id ), 4, '0', STR_PAD_LEFT );
}

/**
 * Payment transfer syntax. The webhook matches this pattern.
 *
 * @param int $order_id Order ID.
 * @return string
 */
function laca_kds_get_payment_content( $order_id ) {
	return laca_kds_get_payment_prefix() . laca_kds_get_payment_order_code( $order_id );
}

/**
 * Return inline CSS variables for the current stall theme.
 *
 * @return string
 */
function laca_kds_get_theme_style_attr() {
	$settings = Laca_KDS_Settings::get_settings();
	$colors   = array(
		'--laca-ink'    => sanitize_hex_color( $settings['theme_ink'] ),
		'--laca-paper'  => sanitize_hex_color( $settings['theme_paper'] ),
		'--laca-accent' => sanitize_hex_color( $settings['theme_accent'] ),
	);
	$rules    = array();

	foreach ( $colors as $name => $value ) {
		if ( $value ) {
			$rules[] = $name . ':' . $value;
		}
	}

	return implode( ';', $rules );
}

/**
 * Enqueue or inject Quicksand for plugin screens.
 *
 * Local theme files are preferred for reliability during live events. If the
 * active theme does not provide Quicksand, the plugin falls back to Google Fonts.
 *
 * @param string $style_handle Existing enqueued style handle to attach inline @font-face to.
 * @return void
 */
function laca_kds_enqueue_quicksand_font( $style_handle ) {
	$weights = array(
		300 => array( 'Light', 'Quicksand-Light.4d3b72ff15.ttf' ),
		400 => array( 'Regular', 'Quicksand-Regular.61504eaec8.ttf' ),
		500 => array( 'Medium', 'Quicksand-Medium.04b8198132.ttf' ),
		600 => array( 'SemiBold', 'Quicksand-SemiBold.339f8d3e09.ttf' ),
		700 => array( 'Bold', 'Quicksand-Bold.111e880f4d.ttf' ),
	);

	$font_faces = array();
	foreach ( $weights as $weight => $font_names ) {
		$variant       = $font_names[0];
		$dist_filename = $font_names[1];
		$font_path     = '';
		$font_url      = '';

		$stylesheet_dist_path = get_stylesheet_directory() . '/dist/fonts/' . $dist_filename;
		if ( file_exists( $stylesheet_dist_path ) ) {
			$font_path = $stylesheet_dist_path;
			$font_url  = get_stylesheet_directory_uri() . '/dist/fonts/' . $dist_filename;
		}

		$template_resource_path = get_template_directory() . '/resources/fonts/Quicksand-' . $variant . '.ttf';
		if ( '' === $font_path && file_exists( $template_resource_path ) ) {
			$font_path = $template_resource_path;
			$font_url  = get_template_directory_uri() . '/resources/fonts/Quicksand-' . $variant . '.ttf';
		}

		if ( '' === $font_path || '' === $font_url ) {
			continue;
		}

		$font_faces[] = sprintf(
			"@font-face{font-family:'Quicksand';src:url('%s') format('truetype');font-weight:%d;font-style:normal;font-display:swap;}",
			esc_url_raw( $font_url ),
			absint( $weight )
		);
	}

	if ( ! empty( $font_faces ) ) {
		wp_add_inline_style( $style_handle, implode( '', $font_faces ) );
		return;
	}

	wp_enqueue_style(
		'laca-kds-quicksand',
		'https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap',
		array(),
		null
	);
}

/**
 * Build a VietQR image URL containing bank account, amount, and transfer content.
 *
 * @param int   $order_id Order ID.
 * @param float $total_amount Total amount.
 * @return string
 */
function laca_kds_get_vietqr_url( $order_id, $total_amount ) {
	$bank_details = laca_kds_get_bank_details();
	$amount       = max( 0, (int) round( (float) $total_amount ) );
	$content      = laca_kds_get_payment_content( $order_id );

	$url = sprintf(
		'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
		rawurlencode( $bank_details['bank_bin'] ),
		rawurlencode( $bank_details['account_number'] ),
		$amount,
		rawurlencode( $content ),
		rawurlencode( $bank_details['account_name'] )
	);

	return esc_url_raw( $url );
}

/**
 * Normalize Vietnamese mobile numbers for SMS providers.
 *
 * @param string $phone Raw phone number.
 * @return string
 */
function laca_kds_normalize_notification_phone( $phone ) {
	$phone = preg_replace( '/[^\d+]/', '', (string) $phone );
	$phone = ltrim( $phone, '+' );

	if ( 0 === strpos( $phone, '0' ) ) {
		return '84' . substr( $phone, 1 );
	}

	return $phone;
}

/**
 * Convert Vietnam phone from 84... to local 0... for providers that prefer it.
 *
 * @param string $phone Normalized phone number.
 * @return string
 */
function laca_kds_notification_phone_to_local( $phone ) {
	$phone = preg_replace( '/\D+/', '', (string) $phone );

	if ( 0 === strpos( $phone, '84' ) && strlen( $phone ) >= 11 ) {
		return '0' . substr( $phone, 2 );
	}

	return $phone;
}

/**
 * Build a notification log row from an HTTP response.
 *
 * @param array|WP_Error $response Provider response.
 * @param array          $base_log Base log fields.
 * @return array
 */
function laca_kds_build_notification_log( $response, $base_log ) {
	if ( is_wp_error( $response ) ) {
		return wp_parse_args(
			array(
				'status'        => 'error',
				'error_message' => $response->get_error_message(),
			),
			$base_log
		);
	}

	$http_code  = (int) wp_remote_retrieve_response_code( $response );
	$body       = (string) wp_remote_retrieve_body( $response );
	$decoded    = json_decode( $body, true );
	$decoded    = is_array( $decoded ) ? $decoded : array();
	$status_raw = isset( $decoded['status'] ) ? strtolower( (string) $decoded['status'] ) : '';
	$code_raw   = isset( $decoded['code'] ) ? (string) $decoded['code'] : '';
	$tran_id    = '';

	if ( isset( $decoded['tranId'] ) ) {
		$tran_id = (string) $decoded['tranId'];
	} elseif ( isset( $decoded['data']['tranId'] ) ) {
		$tran_id = (string) $decoded['data']['tranId'];
	} elseif ( isset( $decoded['data']['tran_id'] ) ) {
		$tran_id = (string) $decoded['data']['tran_id'];
	} elseif ( isset( $decoded['SMSID'] ) ) {
		$tran_id = (string) $decoded['SMSID'];
	}

	$is_success = $http_code >= 200 && $http_code < 300;
	if ( '' !== $status_raw ) {
		$is_success = in_array( $status_raw, array( 'success', 'queued', 'sent', 'ok' ), true );
	}
	if ( isset( $decoded['CodeResponse'] ) ) {
		$code_raw   = (string) $decoded['CodeResponse'];
		$is_success = '100' === $code_raw;
	} elseif ( isset( $decoded['CodeResult'] ) ) {
		$code_raw   = (string) $decoded['CodeResult'];
		$is_success = '100' === $code_raw;
	}
	if ( '' !== $code_raw ) {
		$is_success = $is_success || in_array( $code_raw, array( '00', '0', '100', 'success' ), true );
	}

	return wp_parse_args(
		array(
			'status'                  => $is_success ? 'sent' : 'error',
			'http_code'               => $http_code,
			'provider_code'           => $code_raw ? $code_raw : $status_raw,
			'provider_transaction_id' => $tran_id,
			'response_body'           => $body,
			'error_message'           => $is_success ? '' : $body,
		),
		$base_log
	);
}

/**
 * Render the admin-configured notification message.
 *
 * @param array $order Order data.
 * @param array $settings Plugin settings.
 * @return string
 */
function laca_kds_render_notification_message( $order, $settings ) {
	$template = isset( $settings['notification_message_template'] ) ? (string) $settings['notification_message_template'] : '';

	if ( '' === trim( $template ) ) {
		$template = Laca_KDS_Settings::defaults()['notification_message_template'];
	}

	$message = strtr(
		$template,
		array(
			'{stall_name}' => isset( $settings['stall_name'] ) ? $settings['stall_name'] : '',
			'{order_id}'   => isset( $order['id'] ) ? (string) absint( $order['id'] ) : '',
			'{queue_number}' => isset( $order['id'] ) ? '#' . absint( $order['id'] ) : '',
			'{phone}'      => isset( $order['customer_phone'] ) ? $order['customer_phone'] : '',
			'{total}'      => isset( $order['total_amount'] ) ? number_format_i18n( (float) $order['total_amount'] ) . 'đ' : '',
		)
	);

	return sanitize_textarea_field( $message );
}

/**
 * Send a raw notification request and store the provider response in logs.
 *
 * @param string $phone Raw customer phone.
 * @param string $message Message content.
 * @param int    $order_id Optional order ID.
 * @param array  $order Optional order data for filters.
 * @param array  $settings_override Temporary settings, used by admin test only.
 * @return array|WP_Error
 */
function laca_kds_send_notification_request( $phone, $message, $order_id = 0, $order = array(), $settings_override = array() ) {
	$order_id    = absint( $order_id );
	$settings    = wp_parse_args( is_array( $settings_override ) ? $settings_override : array(), Laca_KDS_Settings::get_settings() );
	$endpoint    = apply_filters( 'laca_kds_notification_endpoint', $settings['notification_api_endpoint'], $order );
	$api_key     = apply_filters( 'laca_kds_notification_api_key', $settings['notification_api_key'], $order );
	$template_id = apply_filters( 'laca_kds_notification_template_id', $settings['notification_template_id'], $order );
	$phone       = laca_kds_normalize_notification_phone( $phone );
	$message     = sanitize_textarea_field( $message );
	$provider    = isset( $settings['notification_provider'] ) ? sanitize_key( $settings['notification_provider'] ) : '';
	$endpoint_host = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
	$base_log    = array(
		'order_id'       => $order_id,
		'customer_phone' => $phone,
		'provider'       => 'notification',
		'message'        => $message,
		'status'         => 'queued',
		'request_json'   => '',
	);

	if ( empty( $endpoint ) && 'esms' !== $provider ) {
		$error = new WP_Error( 'laca_notification_not_configured', __( 'Notification API endpoint is not configured.', 'laca-self-ordering-kds' ) );
		Laca_KDS_Orders_Repository::log_notification_event(
			wp_parse_args(
				array(
					'status'        => 'error',
					'error_message' => $error->get_error_message(),
				),
				$base_log
			)
		);
		return $error;
	}

	if ( empty( $api_key ) ) {
		$error = new WP_Error( 'laca_notification_api_key_missing', __( 'Notification API key is not configured.', 'laca-self-ordering-kds' ) );
		Laca_KDS_Orders_Repository::log_notification_event(
			wp_parse_args(
				array(
					'status'        => 'error',
					'error_message' => $error->get_error_message(),
				),
				$base_log
			)
		);
		return $error;
	}

	if ( 'esms' === $provider || false !== strpos( $endpoint_host, 'esms.vn' ) ) {
		$esms_endpoint = 'https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_post_json/';
		$secret_key    = isset( $settings['notification_secret_key'] ) ? trim( (string) $settings['notification_secret_key'] ) : '';
		$esms_type     = isset( $settings['notification_sms_type'] ) ? absint( $settings['notification_sms_type'] ) : 2;
		$esms_sender   = sanitize_text_field( $settings['notification_sender'] );
		$esms_phone    = laca_kds_notification_phone_to_local( $phone );
		$esms_content  = sanitize_textarea_field( remove_accents( $message ) );

		if ( empty( $secret_key ) ) {
			$error = new WP_Error( 'laca_notification_secret_key_missing', __( 'eSMS Secret Key is not configured.', 'laca-self-ordering-kds' ) );
			Laca_KDS_Orders_Repository::log_notification_event(
				wp_parse_args(
					array(
						'status'        => 'error',
						'error_message' => $error->get_error_message(),
					),
					$base_log
				)
			);
			return $error;
		}

		$payload = array(
			'Phone'     => $esms_phone,
			'Content'   => $esms_content,
			'ApiKey'    => trim( (string) $api_key ),
			'SecretKey' => $secret_key,
			'SmsType'   => $esms_type,
			'IsUnicode' => 0,
		);

		if ( '' !== $esms_sender ) {
			$payload['Brandname'] = $esms_sender;
		}

		$logged_payload = $payload;
		$logged_payload['ApiKey'] = '***';
		$logged_payload['SecretKey'] = '***';
		$logged_payload['ApiKeyLength'] = strlen( trim( (string) $api_key ) );
		$logged_payload['SecretKeyLength'] = strlen( $secret_key );

		$base_log['provider']       = 'esms';
		$base_log['customer_phone'] = $esms_phone;
		$base_log['message']        = $esms_content;
		$base_log['request_json']   = wp_json_encode( $logged_payload );
		error_log( '[Laca KDS eSMS payload] ' . $base_log['request_json'] );

		$response = wp_remote_post(
			$esms_endpoint,
			array(
				'blocking' => true,
				'timeout'  => 8,
				'headers'  => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'     => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[Laca KDS eSMS error] ' . $response->get_error_message() );
		} else {
			error_log( '[Laca KDS eSMS response] ' . print_r( $response, true ) );
			error_log( '[Laca KDS eSMS body] ' . wp_remote_retrieve_body( $response ) );
		}

		Laca_KDS_Orders_Repository::log_notification_event( laca_kds_build_notification_log( $response, $base_log ) );

		return $response;
	}

	if ( 'speedsms' === $provider || false !== strpos( $endpoint_host, 'speedsms.vn' ) ) {
		$speedsms_phone   = preg_replace( '/[^\d]/', '', (string) $phone );
		$speedsms_content = sanitize_textarea_field( remove_accents( $message ) );
		$speedsms_token   = trim( (string) $api_key );
		$speedsms_type    = isset( $settings['notification_sms_type'] ) ? absint( $settings['notification_sms_type'] ) : 2;

		if ( $speedsms_type < 2 || $speedsms_type > 6 ) {
			$speedsms_type = 2;
		}

		$payload = array(
			'to'       => array( $speedsms_phone ),
			'content'  => $speedsms_content,
			'sms_type' => $speedsms_type,
			'sender'   => '',
		);
		$speedsms_sender = sanitize_text_field( $settings['notification_sender'] );
		if ( '' !== $speedsms_sender ) {
			$payload['sender'] = $speedsms_sender;
		} elseif ( 4 === $speedsms_type ) {
			$payload['sender'] = 'Notify';
		}

		$base_log['provider']       = 'speedsms';
		$base_log['customer_phone'] = $speedsms_phone;
		$base_log['message']        = $speedsms_content;
		$base_log['request_json']   = wp_json_encode( $payload );
		error_log( '[Laca KDS SpeedSMS payload] ' . $base_log['request_json'] );

		$response = wp_remote_post(
			$endpoint,
			array(
				'blocking' => true,
				'timeout'  => 8,
				'headers'  => array(
					'Authorization' => 'Basic ' . base64_encode( $speedsms_token . ':x' ),
					'Content-Type'  => 'application/json',
				),
				'body'     => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[Laca KDS SpeedSMS error] ' . $response->get_error_message() );
		} else {
			error_log( '[Laca KDS SpeedSMS response] ' . print_r( $response, true ) );
			error_log( '[Laca KDS SpeedSMS body] ' . wp_remote_retrieve_body( $response ) );
		}

		Laca_KDS_Orders_Repository::log_notification_event( laca_kds_build_notification_log( $response, $base_log ) );

		return $response;
	}

	$payload = array(
		'phone'       => $phone,
		'message'     => $message,
		'template_id' => $template_id,
		'variables'   => array(
			'order_id' => $order_id,
		),
	);
	$base_log['provider']     = 'generic';
	$base_log['request_json'] = wp_json_encode( $payload );
	$response                 = wp_remote_post(
		$endpoint,
		array(
			'blocking' => true,
			'timeout'  => 8,
			'headers'  => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json; charset=utf-8',
			),
			'body'     => wp_json_encode( $payload ),
		)
	);

	Laca_KDS_Orders_Repository::log_notification_event( laca_kds_build_notification_log( $response, $base_log ) );

	return $response;
}

/**
 * Send a completion notification to the order customer.
 *
 * This function is called only by the KDS admin action when an admin marks an
 * order as completed.
 *
 * @param int $order_id Order ID.
 * @return array|WP_Error
 */
function laca_send_notification( $order_id ) {
	$order_id = absint( $order_id );
	$order    = Laca_KDS_Orders_Repository::get_order( $order_id );

	if ( ! $order ) {
		return new WP_Error( 'laca_order_not_found', __( 'Order not found.', 'laca-self-ordering-kds' ) );
	}

	$settings = Laca_KDS_Settings::get_settings();
	$message  = apply_filters( 'laca_kds_notification_message', laca_kds_render_notification_message( $order, $settings ), $order, $settings );

	return laca_kds_send_notification_request( $order['customer_phone'], $message, $order_id, $order );
}

/**
 * Base64 URL-safe encoding.
 *
 * @param string $data Raw bytes.
 * @return string
 */
function laca_kds_base64url_encode( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * Base64 URL-safe decoding.
 *
 * @param string $data Encoded data.
 * @return string
 */
function laca_kds_base64url_decode( $data ) {
	$data = strtr( (string) $data, '-_', '+/' );
	$data .= str_repeat( '=', ( 4 - strlen( $data ) % 4 ) % 4 );

	return (string) base64_decode( $data );
}

/**
 * Convert a VAPID raw private key to an EC PRIVATE KEY PEM.
 *
 * @param string $private_key VAPID private key setting.
 * @return string
 */
function laca_kds_normalize_vapid_private_key( $private_key ) {
	$private_key = trim( (string) $private_key );
	if ( false !== strpos( $private_key, 'BEGIN' ) ) {
		return $private_key;
	}

	$raw = laca_kds_base64url_decode( $private_key );
	if ( 32 !== strlen( $raw ) ) {
		return '';
	}

	$der = "\x30\x31\x02\x01\x01\x04\x20" . $raw . "\xA0\x0A\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";

	return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END EC PRIVATE KEY-----\n";
}

/**
 * Convert an ASN.1 DER ECDSA signature to JOSE R||S format.
 *
 * @param string $signature DER signature.
 * @return string
 */
function laca_kds_ecdsa_der_to_raw( $signature ) {
	$offset = 3;
	if ( ord( $signature[1] ) > 0x80 ) {
		$offset += ord( $signature[1] ) - 0x80;
	}

	$r_length = ord( $signature[ $offset + 1 ] );
	$r        = substr( $signature, $offset + 2, $r_length );
	$offset   = $offset + 2 + $r_length;
	$s_length = ord( $signature[ $offset + 1 ] );
	$s        = substr( $signature, $offset + 2, $s_length );

	$r = str_pad( ltrim( $r, "\x00" ), 32, "\x00", STR_PAD_LEFT );
	$s = str_pad( ltrim( $s, "\x00" ), 32, "\x00", STR_PAD_LEFT );

	return $r . $s;
}

/**
 * Build a VAPID JWT for one push endpoint.
 *
 * @param string $endpoint Push service endpoint.
 * @param array  $settings Plugin settings.
 * @return string
 */
function laca_kds_build_vapid_jwt( $endpoint, $settings ) {
	$origin      = wp_parse_url( $endpoint, PHP_URL_SCHEME ) . '://' . wp_parse_url( $endpoint, PHP_URL_HOST );
	$private_pem = laca_kds_normalize_vapid_private_key( isset( $settings['web_push_private_key'] ) ? $settings['web_push_private_key'] : '' );

	if ( '' === $private_pem ) {
		return '';
	}

	$header  = laca_kds_base64url_encode( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
	$payload = laca_kds_base64url_encode(
		wp_json_encode(
			array(
				'aud' => $origin,
				'exp' => time() + 12 * HOUR_IN_SECONDS,
				'sub' => isset( $settings['web_push_subject'] ) ? $settings['web_push_subject'] : home_url( '/' ),
			)
		)
	);
	$input   = $header . '.' . $payload;
	$key     = openssl_pkey_get_private( $private_pem );

	if ( ! $key || ! openssl_sign( $input, $signature, $key, OPENSSL_ALGO_SHA256 ) ) {
		return '';
	}

	return $input . '.' . laca_kds_base64url_encode( laca_kds_ecdsa_der_to_raw( $signature ) );
}

/**
 * Send Web Push/PWA notifications for a completed order.
 *
 * @param int $order_id Order ID.
 * @return int Number of push attempts.
 */
function laca_send_web_push_notification( $order_id ) {
	$order_id = absint( $order_id );
	$order    = Laca_KDS_Orders_Repository::get_order( $order_id );
	$settings = Laca_KDS_Settings::get_settings();

	if ( ! $order || empty( $settings['web_push_enabled'] ) || empty( $settings['web_push_public_key'] ) || empty( $settings['web_push_private_key'] ) ) {
		return 0;
	}

	$subscriptions = Laca_KDS_Orders_Repository::get_push_subscriptions_for_order( $order_id );
	$attempts      = 0;

	foreach ( $subscriptions as $subscription ) {
		$jwt = laca_kds_build_vapid_jwt( $subscription['endpoint'], $settings );
		if ( '' === $jwt ) {
			continue;
		}

		$attempts++;
		$response = wp_remote_post(
			$subscription['endpoint'],
			array(
				'blocking' => true,
				'timeout'  => 8,
				'headers'  => array(
					'TTL'           => '3600',
					'Urgency'       => 'high',
					'Authorization' => 'vapid t=' . $jwt . ', k=' . sanitize_text_field( $settings['web_push_public_key'] ),
					'Crypto-Key'    => 'p256ecdsa=' . sanitize_text_field( $settings['web_push_public_key'] ),
				),
				'body'     => '',
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$status   = ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) ? 'error' : 'sent';

		if ( in_array( $code, array( 404, 410 ), true ) ) {
			Laca_KDS_Orders_Repository::deactivate_push_subscription( $subscription['id'] );
		}

		Laca_KDS_Orders_Repository::log_notification_event(
			array(
				'order_id'       => $order_id,
				'customer_phone' => $order['customer_phone'],
				'provider'       => 'web_push',
				'message'        => __( 'Web Push: Món của bạn đã sẵn sàng.', 'laca-self-ordering-kds' ),
				'status'         => $status,
				'http_code'      => $code,
				'response_body'  => is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response ),
				'error_message'  => is_wp_error( $response ) ? $response->get_error_message() : '',
				'request_json'   => wp_json_encode( array( 'endpoint_hash' => hash( 'sha256', $subscription['endpoint'] ) ) ),
			)
		);
	}

	return $attempts;
}
