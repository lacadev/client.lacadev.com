<?php
/**
 * REST API endpoints.
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles customer order creation and banking webhook confirmation.
 */
class Laca_KDS_REST_API {
	const MENU_CACHE_VERSION_OPTION = 'laca_kds_menu_cache_version';
	const MENU_CACHE_PREFIX         = 'laca_menu_items_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'save_post_' . Laca_KDS_Food_CPT::POST_TYPE, array( __CLASS__, 'flush_menu_cache' ) );
		add_action( 'save_post_' . Laca_KDS_Combo_CPT::POST_TYPE, array( __CLASS__, 'flush_menu_cache' ) );
		add_action( 'deleted_post', array( __CLASS__, 'maybe_flush_menu_cache_for_post' ) );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_flush_menu_cache_for_post' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'maybe_flush_menu_cache_for_post' ) );
		add_action( 'created_' . Laca_KDS_Food_CPT::TAXONOMY, array( __CLASS__, 'flush_menu_cache' ) );
		add_action( 'edited_' . Laca_KDS_Food_CPT::TAXONOMY, array( __CLASS__, 'flush_menu_cache' ) );
		add_action( 'delete_' . Laca_KDS_Food_CPT::TAXONOMY, array( __CLASS__, 'flush_menu_cache' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'maybe_flush_menu_cache_for_terms' ), 10, 6 );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'laca/v1',
			'/menu-items',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_menu_items' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search'   => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category' => array(
						'sanitize_callback' => 'sanitize_title',
					),
				),
			)
		);

		register_rest_route(
			'laca/v1',
			'/pwa-manifest',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_pwa_manifest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'laca/v1',
			'/orders',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_order' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'laca/v1',
			'/orders/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_order_status' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'order_id' => array(
						'sanitize_callback' => 'absint',
					),
					'token'    => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'laca/v1',
			'/push-subscription',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_push_subscription' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'laca/v1',
			'/pickup-orders',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_pickup_orders' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'laca/v1',
			'/payment-webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'payment_webhook' ),
				'permission_callback' => array( __CLASS__, 'verify_webhook_secret' ),
			)
		);
	}

	/**
	 * Return menu items for mobile category filtering and AJAX search.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_menu_items( WP_REST_Request $request ) {
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$category = sanitize_title( (string) $request->get_param( 'category' ) );
		$items    = self::get_cached_menu_items_array( $search, $category );

		return rest_ensure_response(
			array(
				'items' => $items,
				'count' => count( $items ),
			)
		);
	}

	/**
	 * Return a lightweight PWA manifest for order/menu pages.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_pwa_manifest() {
		$settings = Laca_KDS_Settings::get_settings();

		return rest_ensure_response(
			array(
				'name'             => $settings['stall_name'],
				'short_name'       => $settings['stall_name'],
				'description'      => $settings['stall_description'],
				'start_url'        => Laca_KDS_Settings::get_menu_page_url(),
				'scope'            => home_url( '/' ),
				'display'          => 'standalone',
				'background_color' => $settings['theme_paper'],
				'theme_color'      => $settings['theme_accent'],
			)
		);
	}

	/**
	 * Return cached food and combo menu items.
	 *
	 * @param string $search Search keyword.
	 * @param string $category Category slug.
	 * @return array
	 */
	public static function get_cached_menu_items_array( $search = '', $category = 'all' ) {
		$settings = Laca_KDS_Settings::get_settings();
		$ttl      = isset( $settings['menu_cache_ttl_seconds'] ) ? absint( $settings['menu_cache_ttl_seconds'] ) : 600;

		if ( 0 === $ttl ) {
			return self::get_menu_items_array( $search, $category );
		}

		$cache_key = self::get_menu_cache_key( $search, $category );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$items = self::get_menu_items_array( $search, $category );
		set_transient( $cache_key, $items, min( DAY_IN_SECONDS, $ttl ) );

		return $items;
	}

	/**
	 * Return food and combo menu items for frontend rendering/search.
	 *
	 * @param string $search Search keyword.
	 * @param string $category Category slug.
	 * @return array
	 */
	public static function get_menu_items_array( $search = '', $category = 'all' ) {
		$items = array();
		$types = array(
			Laca_KDS_Food_CPT::POST_TYPE  => array(
				'meta_query' => Laca_KDS_Food_CPT::available_meta_query(),
				'formatter'  => array( __CLASS__, 'format_menu_item' ),
			),
			Laca_KDS_Combo_CPT::POST_TYPE => array(
				'meta_query' => Laca_KDS_Combo_CPT::available_meta_query(),
				'formatter'  => array( __CLASS__, 'format_combo_menu_item' ),
			),
		);

		foreach ( $types as $post_type => $config ) {
			$args = array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => 60,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => true,
				'meta_query'             => $config['meta_query'],
			);

			if ( '' !== $search ) {
				$args['s'] = $search;
			}

			if ( '' !== $category && 'all' !== $category ) {
				$args['tax_query'] = array(
					array(
						'taxonomy' => Laca_KDS_Food_CPT::TAXONOMY,
						'field'    => 'slug',
						'terms'    => $category,
					),
				);
			}

			$query = new WP_Query( $args );
			while ( $query->have_posts() ) {
				$query->the_post();
				$item = call_user_func( $config['formatter'], get_the_ID() );
				if ( ! empty( $item ) ) {
					$items[] = $item;
				}
			}
			wp_reset_postdata();
		}

		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['item_type'] !== $b['item_type'] ) {
					return 'combo' === $a['item_type'] ? -1 : 1;
				}

				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $items;
	}

	/**
	 * Bump the menu cache version so old transients are ignored immediately.
	 *
	 * @return void
	 */
	public static function flush_menu_cache( $unused = null ) {
		unset( $unused );

		update_option( self::MENU_CACHE_VERSION_OPTION, (string) microtime( true ), false );
	}

	/**
	 * Flush menu cache when one of the menu-owned post types changes.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function maybe_flush_menu_cache_for_post( $post_id ) {
		$post_type = get_post_type( absint( $post_id ) );

		if ( in_array( $post_type, array( Laca_KDS_Food_CPT::POST_TYPE, Laca_KDS_Combo_CPT::POST_TYPE ), true ) ) {
			self::flush_menu_cache();
		}
	}

	/**
	 * Flush menu cache when menu categories are assigned or removed.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $terms Term taxonomy IDs.
	 * @param array  $tt_ids Term taxonomy IDs.
	 * @param string $taxonomy Taxonomy slug.
	 * @param bool   $append Whether terms were appended.
	 * @param array  $old_tt_ids Old term taxonomy IDs.
	 * @return void
	 */
	public static function maybe_flush_menu_cache_for_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		unset( $object_id, $terms, $tt_ids, $append, $old_tt_ids );

		if ( Laca_KDS_Food_CPT::TAXONOMY === $taxonomy ) {
			self::flush_menu_cache();
		}
	}

	/**
	 * Build a short transient key for one menu query variant.
	 *
	 * @param string $search Search keyword.
	 * @param string $category Category slug.
	 * @return string
	 */
	private static function get_menu_cache_key( $search, $category ) {
		$version  = (string) get_option( self::MENU_CACHE_VERSION_OPTION, '1' );
		$search   = sanitize_text_field( (string) $search );
		$category = sanitize_title( (string) $category );
		$category = '' === $category ? 'all' : $category;

		return self::MENU_CACHE_PREFIX . md5( wp_json_encode( array( $version, $search, $category ) ) );
	}

	/**
	 * Create a pending order from the customer app.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_order( WP_REST_Request $request ) {
		if ( ! self::check_rate_limit( 'create_order', 20, MINUTE_IN_SECONDS ) ) {
			return new WP_Error(
				'laca_rate_limited',
				__( 'Bạn thao tác hơi nhanh. Vui lòng thử lại sau một chút.', 'laca-self-ordering-kds' ),
				array( 'status' => 429 )
			);
		}

		$nonce = sanitize_text_field( (string) $request->get_header( 'x-laca-nonce' ) );
		if ( ! wp_verify_nonce( $nonce, 'laca_create_order_nonce' ) ) {
			return new WP_Error(
				'laca_invalid_nonce',
				__( 'Invalid request nonce.', 'laca-self-ordering-kds' ),
				array( 'status' => 403 )
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$customer_phone = isset( $params['customer_phone'] ) ? sanitize_text_field( wp_unslash( $params['customer_phone'] ) ) : '';
		$items          = isset( $params['items'] ) && is_array( $params['items'] ) ? $params['items'] : array();
		$selected_gifts = isset( $params['selected_gifts'] ) && is_array( $params['selected_gifts'] ) ? $params['selected_gifts'] : array();

		if ( ! self::is_valid_phone( $customer_phone ) ) {
			return new WP_Error(
				'laca_invalid_phone',
				__( 'Invalid phone number.', 'laca-self-ordering-kds' ),
				array( 'status' => 400 )
			);
		}

		$settings = Laca_KDS_Settings::get_settings();
		if ( ! empty( $settings['max_open_orders'] ) && Laca_KDS_Orders_Repository::count_open_orders() >= (int) $settings['max_open_orders'] ) {
			return new WP_Error(
				'laca_backlog_full',
				$settings['backlog_message'],
				array( 'status' => 429 )
			);
		}

		$prepared_order = self::prepare_order_items( $items, $selected_gifts );
		if ( is_wp_error( $prepared_order ) ) {
			return $prepared_order;
		}

		$payment_timeout = absint( $settings['payment_timeout_seconds'] );
		$expires_at      = $payment_timeout > 0 ? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $payment_timeout ) : '';
		$order_id = Laca_KDS_Orders_Repository::create_order(
			$customer_phone,
			$prepared_order['items'],
			$prepared_order['total_amount'],
			$expires_at
		);

		if ( ! $order_id ) {
			return new WP_Error(
				'laca_order_create_failed',
				__( 'Could not create order.', 'laca-self-ordering-kds' ),
				array( 'status' => 500 )
			);
		}

		$bank_details     = laca_kds_get_bank_details();
		$transfer_content = laca_kds_get_payment_content( $order_id );
		$order            = Laca_KDS_Orders_Repository::get_order( $order_id );
		$status_url       = add_query_arg(
			array(
				'order_id' => $order_id,
				'token'    => $order ? $order['public_token'] : '',
			),
			Laca_KDS_Settings::get_status_page_url()
		);

		return rest_ensure_response(
			array(
				'order_id'         => $order_id,
				'queue_number'     => '#' . $order_id,
				'subtotal'         => $prepared_order['subtotal'],
				'discounts'        => $prepared_order['discounts'],
				'total_amount'     => $prepared_order['total_amount'],
				'items'            => $prepared_order['items'],
				'status'           => 'pending',
				'expires_at'       => $order ? $order['expires_at'] : $expires_at,
				'expires_in'       => $order ? Laca_KDS_Orders_Repository::seconds_until_expiry( $order ) : $payment_timeout,
				'transfer_content' => $transfer_content,
				'qr_url'           => laca_kds_get_vietqr_url( $order_id, $prepared_order['total_amount'] ),
				'status_url'       => esc_url_raw( $status_url ),
				'status_token'     => $order ? $order['public_token'] : '',
				'bank'             => array(
					'bank_bin'       => $bank_details['bank_bin'],
					'account_number' => $bank_details['account_number'],
					'account_name'   => $bank_details['account_name'],
				),
			)
		);
	}

	/**
	 * Return public order status using the order token.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_order_status( WP_REST_Request $request ) {
		$order_id = absint( $request->get_param( 'order_id' ) );
		$token    = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$order    = Laca_KDS_Orders_Repository::get_order_by_token( $order_id, $token );

		if ( ! $order ) {
			return new WP_Error(
				'laca_order_status_not_found',
				__( 'Order not found.', 'laca-self-ordering-kds' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'order_id'      => $order['id'],
				'queue_number'  => '#' . $order['id'],
				'masked_phone'  => $order['masked_phone'],
				'status'        => $order['status'],
				'total_amount'  => $order['total_amount'],
				'expires_at'    => $order['expires_at'],
				'expires_in'    => Laca_KDS_Orders_Repository::seconds_until_expiry( $order ),
				'created_at'    => $order['created_at'],
				'updated_at'    => $order['updated_at'],
				'completed_at'  => $order['completed_at'],
			)
		);
	}

	/**
	 * Store a web push subscription for a public order status page.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_push_subscription( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();

		$order_id     = isset( $params['order_id'] ) ? absint( $params['order_id'] ) : 0;
		$token        = isset( $params['token'] ) ? sanitize_text_field( wp_unslash( $params['token'] ) ) : '';
		$subscription = isset( $params['subscription'] ) && is_array( $params['subscription'] ) ? $params['subscription'] : array();
		$order        = Laca_KDS_Orders_Repository::get_order_by_token( $order_id, $token );

		if ( ! $order ) {
			return new WP_Error(
				'laca_push_order_not_found',
				__( 'Order not found.', 'laca-self-ordering-kds' ),
				array( 'status' => 404 )
			);
		}

		$subscription_id = Laca_KDS_Orders_Repository::upsert_push_subscription( $order_id, $token, $subscription );
		if ( ! $subscription_id ) {
			return new WP_Error(
				'laca_push_subscription_failed',
				__( 'Could not save push subscription.', 'laca-self-ordering-kds' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success'         => true,
				'subscription_id' => $subscription_id,
			)
		);
	}

	/**
	 * Return completed orders for the pickup display.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_pickup_orders() {
		$orders = array_map(
			static function ( $order ) {
				return array(
					'order_id'     => $order['id'],
					'queue_number' => '#' . $order['id'],
					'masked_phone' => $order['masked_phone'],
					'completed_at' => $order['completed_at'],
				);
			},
			Laca_KDS_Orders_Repository::get_recent_completed_orders( 30 )
		);

		return rest_ensure_response(
			array(
				'orders' => $orders,
				'count'  => count( $orders ),
			)
		);
	}

	/**
	 * Verify the banking webhook shared secret.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public static function verify_webhook_secret( WP_REST_Request $request ) {
		$expected = (string) apply_filters( 'laca_kds_webhook_secret', Laca_KDS_Settings::get( 'webhook_secret' ) );
		$provided = sanitize_text_field( (string) $request->get_header( 'x-laca-webhook-secret' ) );
		$auth     = sanitize_text_field( (string) $request->get_header( 'authorization' ) );

		if ( '' === $provided && 0 === stripos( $auth, 'Apikey ' ) ) {
			$provided = trim( substr( $auth, 7 ) );
		}
		if ( '' === $provided && 0 === stripos( $auth, 'Bearer ' ) ) {
			$provided = trim( substr( $auth, 7 ) );
		}
		if ( '' === $provided && '' !== $auth ) {
			$provided = trim( $auth );
		}

		if ( '' === $expected ) {
			return new WP_Error(
				'laca_webhook_secret_not_configured',
				__( 'Webhook secret is not configured.', 'laca-self-ordering-kds' ),
				array( 'status' => 503 )
			);
		}

		if ( '' === $expected || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'laca_webhook_forbidden',
				__( 'Invalid webhook secret.', 'laca-self-ordering-kds' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Confirm a payment from a banking provider webhook.
	 *
	 * Expected transfer syntax: "PREFIX0001" or "PREFIX 0001".
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function payment_webhook( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}

		$amount = self::extract_payload_value( $payload, array( 'transferAmount', 'amount', 'totalAmount', 'creditAmount', 'money', 'value' ) );
		$amount = (float) $amount;
		$transfer_type = sanitize_key( (string) self::extract_payload_value( $payload, array( 'transfer_type', 'transferType', 'type' ) ) );

		$payment_prefix = laca_kds_get_payment_prefix();
		$syntax_pattern = '/\b' . preg_quote( $payment_prefix, '/' ) . '[\s\-_:]*(?:#|\[)?0*(\d+)\]?\b/i';
		$content        = '';
		$order_id       = 0;
		$content_keys   = array( 'payment_code', 'paymentCode', 'content', 'description', 'transferContent', 'transaction_content', 'paymentContent', 'remark', 'code', 'reference_code', 'referenceCode' );

		foreach ( $content_keys as $content_key ) {
			$candidate = sanitize_text_field( (string) self::extract_payload_value( $payload, array( $content_key ) ) );
			if ( '' === $candidate ) {
				continue;
			}

			if ( '' === $content ) {
				$content = $candidate;
			}

			if ( preg_match( $syntax_pattern, $candidate, $matches ) ) {
				$content  = $candidate;
				$order_id = absint( $matches[1] );
				break;
			}
		}

		if ( in_array( $transfer_type, array( 'debit', 'out', 'withdraw' ), true ) ) {
			Laca_KDS_Orders_Repository::log_payment_event(
				array(
					'provider'     => 'webhook',
					'amount'       => $amount,
					'content'      => $content,
					'status'       => 'ignored_debit',
					'payload_json' => wp_json_encode( $payload ),
				)
			);
			return rest_ensure_response(
				array(
					'success' => true,
					'matched' => false,
					'status'  => 'ignored_debit',
				)
			);
		}

		if ( $amount <= 0 || ! $order_id ) {
			Laca_KDS_Orders_Repository::log_payment_event(
				array(
					'provider'     => 'webhook',
					'amount'       => $amount,
					'content'      => $content,
					'status'       => 'invalid',
					'payload_json' => wp_json_encode( $payload ),
				)
			);
			return rest_ensure_response(
				array(
					'success' => true,
					'matched' => false,
					'status'  => 'invalid',
				)
			);
		}

		$order = Laca_KDS_Orders_Repository::get_order( $order_id );

		if ( ! $order ) {
			Laca_KDS_Orders_Repository::log_payment_event(
				array(
					'order_id'     => $order_id,
					'provider'     => 'webhook',
					'amount'       => $amount,
					'content'      => $content,
					'status'       => 'order_not_found',
					'payload_json' => wp_json_encode( $payload ),
				)
			);
			return rest_ensure_response(
				array(
					'success' => true,
					'matched' => false,
					'status'  => 'order_not_found',
				)
			);
		}

		if ( in_array( $order['status'], array( 'paid', 'completed' ), true ) ) {
			Laca_KDS_Orders_Repository::log_payment_event(
				array(
					'order_id'     => $order_id,
					'provider'     => 'webhook',
					'amount'       => $amount,
					'content'      => $content,
					'status'       => 'completed' === $order['status'] ? 'already_completed' : 'already_paid',
					'payload_json' => wp_json_encode( $payload ),
				)
			);
			return rest_ensure_response(
				array(
					'success' => true,
				)
			);
		}

		if ( ! in_array( $order['status'], array( 'pending', 'canceled' ), true ) ) {
			Laca_KDS_Orders_Repository::log_payment_event(
				array(
					'order_id'     => $order_id,
					'provider'     => 'webhook',
					'amount'       => $amount,
					'content'      => $content,
					'status'       => 'invalid_status',
					'payload_json' => wp_json_encode( $payload ),
				)
			);
			return rest_ensure_response(
				array(
					'success' => true,
					'matched' => false,
					'status'  => 'invalid_status',
				)
			);
		}

		$expected_amount = (int) round( (float) $order['total_amount'] );
		$received_amount = (int) round( $amount );

		if ( $expected_amount !== $received_amount ) {
			Laca_KDS_Orders_Repository::log_payment_event(
				array(
					'order_id'     => $order_id,
					'provider'     => 'webhook',
					'amount'       => $amount,
					'content'      => $content,
					'status'       => 'amount_mismatch',
					'payload_json' => wp_json_encode( $payload ),
				)
			);
			return rest_ensure_response(
				array(
					'success' => true,
					'matched' => false,
					'status'  => 'amount_mismatch',
				)
			);
		}

		Laca_KDS_Orders_Repository::update_status(
			$order_id,
			'paid',
			array(
				'payment_method' => 'webhook',
				'expires_at'     => '',
			)
		);
		Laca_KDS_Orders_Repository::log_payment_event(
			array(
				'order_id'     => $order_id,
				'provider'     => 'webhook',
				'amount'       => $amount,
				'content'      => $content,
				'status'       => 'canceled' === $order['status'] ? 'matched_after_expiry' : 'matched',
				'payload_json' => wp_json_encode( $payload ),
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'matched' => true,
				'status'  => 'matched',
			)
		);
	}

	/**
	 * Validate Vietnamese-style phone input without being too restrictive.
	 *
	 * @param string $phone Phone number.
	 * @return bool
	 */
	private static function is_valid_phone( $phone ) {
		return (bool) preg_match( '/^[0-9+()\-\s.]{8,20}$/', $phone );
	}

	/**
	 * Format one food post for frontend rendering.
	 *
	 * @param int $food_id Food post ID.
	 * @return array
	 */
	private static function format_menu_item( $food_id ) {
		$food_id    = absint( $food_id );
		$categories = get_the_terms( $food_id, Laca_KDS_Food_CPT::TAXONOMY );
		$categories = is_array( $categories ) ? array_map(
			static function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => sanitize_text_field( $term->name ),
					'slug' => sanitize_title( $term->slug ),
				);
			},
			$categories
		) : array();

		return array(
			'id'            => $food_id,
			'item_id'       => $food_id,
			'item_type'     => 'food',
			'food_id'       => $food_id,
			'combo_id'      => 0,
			'name'          => get_the_title( $food_id ),
			'price'         => Laca_KDS_Food_CPT::get_price( $food_id ),
			'regular_price' => Laca_KDS_Food_CPT::get_price( $food_id ),
			'savings'       => 0,
			'thumbnail_url' => get_the_post_thumbnail_url( $food_id, 'medium' ),
			'categories'    => $categories,
			'variants'      => Laca_KDS_Food_CPT::get_variants( $food_id ),
			'is_combo'      => false,
			'combo_badge'   => '',
			'combo_details' => '',
			'combo_items'   => array(),
		);
	}

	/**
	 * Format one combo for the customer menu.
	 *
	 * @param int $combo_id Combo post ID.
	 * @return array
	 */
	private static function format_combo_menu_item( $combo_id ) {
		$combo_id = absint( $combo_id );
		if ( ! Laca_KDS_Combo_CPT::is_available( $combo_id ) ) {
			return array();
		}

		$categories = get_the_terms( $combo_id, Laca_KDS_Food_CPT::TAXONOMY );
		$categories = is_array( $categories ) ? array_map(
			static function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => sanitize_text_field( $term->name ),
					'slug' => sanitize_title( $term->slug ),
				);
			},
			$categories
		) : array();

		$price          = Laca_KDS_Combo_CPT::get_price( $combo_id );
		$regular_price  = Laca_KDS_Combo_CPT::get_original_price( $combo_id );
		$combo_items    = Laca_KDS_Combo_CPT::get_items( $combo_id );
		$combo_details  = Laca_KDS_Combo_CPT::get_details( $combo_id );
		$thumbnail_url  = get_the_post_thumbnail_url( $combo_id, 'medium' );

		if ( ! $thumbnail_url && ! empty( $combo_items[0]['food_id'] ) ) {
			$thumbnail_url = get_the_post_thumbnail_url( absint( $combo_items[0]['food_id'] ), 'medium' );
		}

		return array(
			'id'            => $combo_id,
			'item_id'       => $combo_id,
			'item_type'     => 'combo',
			'food_id'       => 0,
			'combo_id'      => $combo_id,
			'name'          => get_the_title( $combo_id ),
			'price'         => $price,
			'regular_price' => $regular_price,
			'savings'       => max( 0, $regular_price - $price ),
			'thumbnail_url' => $thumbnail_url,
			'categories'    => $categories,
			'variants'      => array(),
			'is_combo'      => true,
			'combo_badge'   => Laca_KDS_Combo_CPT::get_badge( $combo_id ),
			'combo_details' => $combo_details,
			'combo_items'   => $combo_items,
		);
	}

	/**
	 * Rebuild trusted order items from published CPT data.
	 *
	 * @param array $items Raw requested items.
	 * @return array|WP_Error
	 */
	private static function prepare_order_items( $items, $selected_gifts = array() ) {
		$prepared = array();
		$subtotal = 0;
		$total_quantity = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$item_type = isset( $item['item_type'] ) ? sanitize_key( $item['item_type'] ) : 'food';
			$item_id   = isset( $item['item_id'] ) ? absint( $item['item_id'] ) : 0;
			$food_id   = isset( $item['food_id'] ) ? absint( $item['food_id'] ) : 0;
			$combo_id  = isset( $item['combo_id'] ) ? absint( $item['combo_id'] ) : 0;
			$quantity  = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
			$quantity  = min( 99, max( 0, $quantity ) );

			if ( 'combo' === $item_type ) {
				$combo_id = $combo_id ? $combo_id : $item_id;
				if ( ! $combo_id || ! $quantity || Laca_KDS_Combo_CPT::POST_TYPE !== get_post_type( $combo_id ) || ! Laca_KDS_Combo_CPT::is_available( $combo_id ) ) {
					continue;
				}

				$price          = Laca_KDS_Combo_CPT::get_price( $combo_id );
				$regular_price  = Laca_KDS_Combo_CPT::get_original_price( $combo_id );
				$line_total     = $price * $quantity;
				$subtotal      += $line_total;
				$total_quantity += $quantity;

				$prepared[] = array(
					'item_type'           => 'combo',
					'food_id'             => 0,
					'combo_id'            => $combo_id,
					'name'                => get_the_title( $combo_id ),
					'quantity'            => $quantity,
					'price'               => $price,
					'regular_price'       => $regular_price,
					'line_total'          => $line_total,
					'original_line_total' => $regular_price * $quantity,
					'combo_badge'         => Laca_KDS_Combo_CPT::get_badge( $combo_id ),
					'combo_details'       => Laca_KDS_Combo_CPT::get_details( $combo_id ),
					'combo_items'         => Laca_KDS_Combo_CPT::get_items( $combo_id ),
				);
				continue;
			}

			$food_id = $food_id ? $food_id : $item_id;

			if ( ! $food_id || ! $quantity || 'publish' !== get_post_status( $food_id ) || Laca_KDS_Food_CPT::POST_TYPE !== get_post_type( $food_id ) ) {
				continue;
			}

			if ( ! Laca_KDS_Food_CPT::is_available( $food_id ) ) {
				continue;
			}

			$variant_result = Laca_KDS_Food_CPT::prepare_selected_variants( $food_id, isset( $item['variants'] ) && is_array( $item['variants'] ) ? $item['variants'] : array() );
			$base_price = Laca_KDS_Food_CPT::get_price( $food_id );
			$price      = max( 0, $base_price + (float) $variant_result['price_delta'] );
			$line_total = $price * $quantity;
			$subtotal  += $line_total;
			$total_quantity += $quantity;

			$prepared[] = array(
				'item_type'     => 'food',
				'food_id'       => $food_id,
				'combo_id'      => 0,
				'name'          => get_the_title( $food_id ),
				'quantity'      => $quantity,
				'price'         => $price,
				'base_price'    => $base_price,
				'line_total'    => $line_total,
				'variants'      => $variant_result['selected'],
			);
		}

		if ( empty( $prepared ) || $subtotal <= 0 ) {
			return new WP_Error(
				'laca_empty_order',
				__( 'Order must contain at least one valid item.', 'laca-self-ordering-kds' ),
				array( 'status' => 400 )
			);
		}

		$promotion_result = self::apply_order_promotions( $prepared, $subtotal, $total_quantity, $selected_gifts );
		if ( $promotion_result['total_amount'] <= 0 ) {
			return new WP_Error(
				'laca_discount_total_zero',
				__( 'Cấu hình khuyến mãi đang làm tổng đơn về 0đ. Vui lòng báo quầy kiểm tra lại ưu đãi.', 'laca-self-ordering-kds' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'items'        => $promotion_result['items'],
			'subtotal'     => $subtotal,
			'discounts'    => $promotion_result['discounts'],
			'total_amount' => $promotion_result['total_amount'],
		);
	}

	/**
	 * Apply configured discounts and gifts to trusted order items.
	 *
	 * @param array $items Prepared food items.
	 * @param float $subtotal Original subtotal.
	 * @param int   $total_quantity Total quantity.
	 * @return array
	 */
	private static function apply_order_promotions( $items, $subtotal, $total_quantity, $selected_gifts = array() ) {
		$rules          = Laca_KDS_Settings::get_promotion_rules();
		$discounts      = array();
		$total_discount = 0;
		$selected_gifts = self::normalize_selected_gifts( $selected_gifts );

		foreach ( $rules as $rule_index => $rule ) {
			if ( empty( $rule['enabled'] ) || ! self::promotion_rule_matches( $rule, $subtotal, $total_quantity ) ) {
				continue;
			}

			if ( 'gift' === $rule['reward_type'] ) {
				$gift_item = self::prepare_selected_gift_item( $rule, isset( $selected_gifts[ $rule_index ] ) ? $selected_gifts[ $rule_index ] : 0 );
				$items[] = array(
					'item_type'  => 'gift',
					'food_id'    => $gift_item['food_id'],
					'combo_id'   => 0,
					'name'       => $gift_item['name'],
					'quantity'   => 1,
					'price'      => 0,
					'line_total' => 0,
				);
				continue;
			}

			$discount_type = 'discount_percent' === $rule['reward_type'] ? 'percent' : 'fixed';
			$amount        = self::calculate_discount_amount( $subtotal, $discount_type, (float) $rule['discount_value'] );
			if ( $amount <= 0 ) {
				continue;
			}

			$discounts[] = array(
				'label'  => sanitize_text_field( $rule['label'] ),
				'amount' => $amount,
			);
			$total_discount += $amount;
		}

		$remaining_discount = min( $subtotal, $total_discount );
		$applied_discounts  = array();
		foreach ( $discounts as $discount ) {
			$discount_amount = min( $remaining_discount, (float) $discount['amount'] );
			if ( $discount_amount <= 0 ) {
				continue;
			}

			$items[] = array(
				'item_type'  => 'discount',
				'food_id'    => 0,
				'combo_id'   => 0,
				'name'       => $discount['label'],
				'quantity'   => 1,
				'price'      => -1 * $discount_amount,
				'line_total' => -1 * $discount_amount,
			);
			$applied_discounts[] = array(
				'label'  => $discount['label'],
				'amount' => $discount_amount,
			);
			$remaining_discount -= $discount_amount;
		}
		$total_discount = min( $subtotal, $total_discount );

		return array(
			'items'        => $items,
			'discounts'    => $applied_discounts,
			'total_amount' => max( 0, $subtotal - $total_discount ),
		);
	}

	/**
	 * Normalize selected gift IDs keyed by promotion rule index.
	 *
	 * @param array $selected_gifts Raw selected gifts.
	 * @return array
	 */
	private static function normalize_selected_gifts( $selected_gifts ) {
		$normalized = array();

		foreach ( $selected_gifts as $gift ) {
			if ( ! is_array( $gift ) ) {
				continue;
			}

			$rule_index = isset( $gift['rule_index'] ) ? absint( $gift['rule_index'] ) : 0;
			$food_id    = isset( $gift['food_id'] ) ? absint( $gift['food_id'] ) : 0;

			if ( $food_id ) {
				$normalized[ $rule_index ] = $food_id;
			}
		}

		return $normalized;
	}

	/**
	 * Validate and format one selected gift item.
	 *
	 * @param array $rule Promotion rule.
	 * @param int   $selected_food_id Selected gift food ID.
	 * @return array
	 */
	private static function prepare_selected_gift_item( $rule, $selected_food_id ) {
		$gift_options = isset( $rule['gift_options'] ) && is_array( $rule['gift_options'] ) ? $rule['gift_options'] : array();
		$allowed_ids   = wp_list_pluck( $gift_options, 'id' );
		$food_id       = absint( $selected_food_id );

		if ( empty( $allowed_ids ) ) {
			return array(
				'food_id' => 0,
				'name'    => isset( $rule['gift_text'] ) && '' !== $rule['gift_text'] ? sanitize_text_field( $rule['gift_text'] ) : __( 'Quà tặng', 'laca-self-ordering-kds' ),
			);
		}

		if ( ! in_array( $food_id, array_map( 'absint', $allowed_ids ), true ) ) {
			$food_id = absint( $allowed_ids[0] );
		}

		if ( ! $food_id || Laca_KDS_Food_CPT::POST_TYPE !== get_post_type( $food_id ) || 'publish' !== get_post_status( $food_id ) || ! Laca_KDS_Food_CPT::is_available( $food_id ) ) {
			return array(
				'food_id' => 0,
				'name'    => __( 'Quà tặng', 'laca-self-ordering-kds' ),
			);
		}

		return array(
			'food_id' => $food_id,
			'name'    => get_the_title( $food_id ),
		);
	}

	/**
	 * Calculate a fixed or percentage discount.
	 *
	 * @param float  $subtotal Order subtotal.
	 * @param string $type fixed|percent.
	 * @param float  $value Discount value.
	 * @return float
	 */
	private static function calculate_discount_amount( $subtotal, $type, $value ) {
		if ( 'percent' === sanitize_key( $type ) ) {
			return min( $subtotal, $subtotal * min( 100, max( 0, $value ) ) / 100 );
		}

		return min( $subtotal, max( 0, $value ) );
	}

	/**
	 * Whether one promotion rule matches the current cart.
	 *
	 * @param array $rule Promotion rule.
	 * @param float $subtotal Cart subtotal.
	 * @param int   $total_quantity Cart quantity.
	 * @return bool
	 */
	private static function promotion_rule_matches( $rule, $subtotal, $total_quantity ) {
		if ( 'total' === sanitize_key( $rule['trigger_type'] ) ) {
			$min_total = isset( $rule['min_total'] ) ? (float) $rule['min_total'] : 0;

			return $min_total > 0 && $subtotal >= $min_total;
		}

		$min_qty = isset( $rule['min_qty'] ) ? absint( $rule['min_qty'] ) : 0;

		return $min_qty > 0 && $total_quantity >= $min_qty;
	}

	/**
	 * Recursively extract the first matching payload key.
	 *
	 * @param mixed $payload Payload.
	 * @param array $keys Keys to search for.
	 * @return mixed|null
	 */
	private static function extract_payload_value( $payload, $keys ) {
		if ( ! is_array( $payload ) ) {
			return null;
		}

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $payload ) ) {
				return $payload[ $key ];
			}
		}

		foreach ( $payload as $value ) {
			if ( is_array( $value ) ) {
				$found = self::extract_payload_value( $value, $keys );
				if ( null !== $found && '' !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Basic transient rate limiting for public order creation.
	 *
	 * @param string $action Action key.
	 * @param int    $limit Max requests.
	 * @param int    $window Window in seconds.
	 * @return bool
	 */
	private static function check_rate_limit( $action, $limit, $window ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'laca_rate_' . md5( $action . '|' . $ip );
		$hit = (int) get_transient( $key );

		if ( $hit >= $limit ) {
			return false;
		}

		set_transient( $key, $hit + 1, $window );

		return true;
	}
}
