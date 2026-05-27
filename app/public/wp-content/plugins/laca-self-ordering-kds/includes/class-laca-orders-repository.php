<?php
/**
 * Custom order table repository.
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles persistence for the wp_laca_orders table.
 */
class Laca_KDS_Orders_Repository {
	const SCHEMA_VERSION = '6';

	/**
	 * Allowed order statuses.
	 *
	 * @return string[]
	 */
	public static function allowed_statuses() {
		return array( 'pending', 'paid', 'completed', 'canceled' );
	}

	/**
	 * Create or update the custom order table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = laca_kds_orders_table_name();
		$logs_table_name = laca_kds_payment_logs_table_name();
		$sms_table_name  = laca_kds_notification_logs_table_name();
		$push_table_name = laca_kds_push_subscriptions_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_phone varchar(32) NOT NULL,
			total_amount decimal(12,2) NOT NULL DEFAULT 0.00,
			order_items longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			previous_status varchar(20) NOT NULL DEFAULT '',
			payment_method varchar(30) NOT NULL DEFAULT '',
			public_token varchar(64) NOT NULL DEFAULT '',
			expires_at datetime NULL DEFAULT NULL,
			paid_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			completed_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY customer_phone (customer_phone),
			KEY status (status),
			KEY public_token (public_token),
			KEY expires_at (expires_at),
			KEY paid_at (paid_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		$logs_sql = "CREATE TABLE {$logs_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			provider varchar(50) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			content text NOT NULL,
			status varchar(30) NOT NULL DEFAULT '',
			payload_json longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $logs_sql );

		$sms_sql = "CREATE TABLE {$sms_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_phone varchar(32) NOT NULL DEFAULT '',
			provider varchar(50) NOT NULL DEFAULT '',
			message text NOT NULL,
			status varchar(30) NOT NULL DEFAULT '',
			http_code int(11) NOT NULL DEFAULT 0,
			provider_code varchar(50) NOT NULL DEFAULT '',
			provider_transaction_id varchar(100) NOT NULL DEFAULT '',
			request_json longtext NOT NULL,
			response_body longtext NOT NULL,
			error_message text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY customer_phone (customer_phone),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sms_sql );

		$push_sql = "CREATE TABLE {$push_table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			public_token varchar(64) NOT NULL DEFAULT '',
			endpoint_hash varchar(64) NOT NULL DEFAULT '',
			endpoint text NOT NULL,
			p256dh varchar(255) NOT NULL DEFAULT '',
			auth varchar(255) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY endpoint_hash (endpoint_hash),
			KEY order_id (order_id),
			KEY public_token (public_token),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset_collate};";

		dbDelta( $push_sql );
		update_option( 'laca_kds_schema_version', self::SCHEMA_VERSION, false );
	}

	/**
	 * Upgrade custom tables if the plugin code is newer than the DB schema.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_schema() {
		if ( self::SCHEMA_VERSION !== get_option( 'laca_kds_schema_version' ) ) {
			self::create_table();
			self::repair_cash_completed_orders();
		}
	}

	/**
	 * Clear accidental paid_at values from older cash/offline completions.
	 *
	 * @return void
	 */
	private static function repair_cash_completed_orders() {
		global $wpdb;

		$wpdb->query(
			"UPDATE " . laca_kds_orders_table_name() . " SET paid_at = NULL WHERE status = 'completed' AND previous_status = 'pending' AND payment_method = ''"
		);
	}

	/**
	 * Insert a new order.
	 *
	 * @param string $customer_phone Customer phone number.
	 * @param array  $order_items Order items.
	 * @param float  $total_amount Total amount.
	 * @param string $expires_at Order payment expiry time.
	 * @return int|false
	 */
	public static function create_order( $customer_phone, $order_items, $total_amount, $expires_at = '' ) {
		global $wpdb;

		$now          = current_time( 'mysql' );
		$public_token = wp_generate_password( 32, false, false );
		$expires_at   = '' !== $expires_at ? sanitize_text_field( $expires_at ) : null;

		$inserted = $wpdb->insert(
			laca_kds_orders_table_name(),
			array(
				'customer_phone' => sanitize_text_field( $customer_phone ),
				'total_amount'   => (float) $total_amount,
				'order_items'    => wp_json_encode( array_values( $order_items ) ),
				'status'         => 'pending',
				'public_token'   => $public_token,
				'expires_at'     => $expires_at,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch an order by public token for customer status views.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $public_token Public token.
	 * @return array|null
	 */
	public static function get_order_by_token( $order_id, $public_token ) {
		global $wpdb;

		self::cancel_expired_pending_orders();

		$order_id     = absint( $order_id );
		$public_token = sanitize_text_field( $public_token );

		if ( ! $order_id || '' === $public_token ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . laca_kds_orders_table_name() . ' WHERE id = %d AND public_token = %s',
				$order_id,
				$public_token
			),
			ARRAY_A
		);

		return $row ? self::format_order_row( $row ) : null;
	}

	/**
	 * Fetch one order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public static function get_order( $order_id ) {
		global $wpdb;

		self::cancel_expired_pending_orders();

		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . laca_kds_orders_table_name() . ' WHERE id = %d',
				$order_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return self::format_order_row( $row );
	}

	/**
	 * Fetch orders that still need KDS action.
	 *
	 * @param int $limit Maximum number of orders.
	 * @return array
	 */
	public static function get_open_orders( $limit = 100 ) {
		global $wpdb;

		self::cancel_expired_pending_orders();

		$limit = min( 200, max( 1, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . laca_kds_orders_table_name() . " WHERE status IN ('pending', 'paid') ORDER BY created_at ASC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		return array_map( array( __CLASS__, 'format_order_row' ), $rows );
	}

	/**
	 * Count pending and paid orders.
	 *
	 * @return int
	 */
	public static function count_open_orders() {
		global $wpdb;

		self::cancel_expired_pending_orders();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . laca_kds_orders_table_name() . " WHERE status IN ('pending', 'paid')" );
	}

	/**
	 * Fetch recently closed orders so admins can undo accidental actions.
	 *
	 * @param int $limit Maximum number of orders.
	 * @return array
	 */
	public static function get_recent_closed_orders( $limit = 20 ) {
		global $wpdb;

		self::cancel_expired_pending_orders();

		$limit = min( 50, max( 1, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . laca_kds_orders_table_name() . " WHERE status IN ('completed', 'canceled') AND updated_at >= %s ORDER BY updated_at DESC LIMIT %d",
				current_time( 'Y-m-d' ) . ' 00:00:00',
				$limit
			),
			ARRAY_A
		);

		return $rows ? array_map( array( __CLASS__, 'format_order_row' ), $rows ) : array();
	}

	/**
	 * Fetch recently completed orders for pickup display.
	 *
	 * @param int $limit Maximum number of orders.
	 * @return array
	 */
	public static function get_recent_completed_orders( $limit = 30 ) {
		global $wpdb;

		$limit = min( 100, max( 1, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . laca_kds_orders_table_name() . " WHERE status = 'completed' AND created_at >= %s ORDER BY COALESCE(completed_at, updated_at, created_at) DESC LIMIT %d",
				current_time( 'Y-m-d' ) . ' 00:00:00',
				$limit
			),
			ARRAY_A
		);

		return $rows ? array_map( array( __CLASS__, 'format_order_row' ), $rows ) : array();
	}

	/**
	 * Update order status.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status New status.
	 * @param array  $args Extra fields.
	 * @return bool
	 */
	public static function update_status( $order_id, $status, $args = array() ) {
		global $wpdb;

		$order_id = absint( $order_id );
		$status   = sanitize_key( $status );

		if ( ! $order_id || ! in_array( $status, self::allowed_statuses(), true ) ) {
			return false;
		}

		$current = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT status, paid_at FROM ' . laca_kds_orders_table_name() . ' WHERE id = %d',
				$order_id
			),
			ARRAY_A
		);

		if ( ! $current ) {
			return false;
		}

		$now     = current_time( 'mysql' );
		$data    = array(
			'status'          => $status,
			'previous_status' => sanitize_key( $current['status'] ),
			'updated_at'      => $now,
		);
		$formats = array( '%s', '%s', '%s' );

		if ( 'completed' === $status ) {
			$data['completed_at'] = $now;
			$formats[]            = '%s';
		} else {
			$data['completed_at'] = null;
			$formats[]            = '%s';
		}

		if ( 'paid' === $status && empty( $current['paid_at'] ) ) {
			$data['paid_at'] = $now;
			$formats[]       = '%s';
		}

		if ( isset( $args['payment_method'] ) ) {
			$data['payment_method'] = sanitize_key( $args['payment_method'] );
			$formats[]              = '%s';
		}

		if ( array_key_exists( 'expires_at', $args ) ) {
			$data['expires_at'] = $args['expires_at'] ? sanitize_text_field( $args['expires_at'] ) : null;
			$formats[]          = '%s';
		}

		$updated = $wpdb->update(
			laca_kds_orders_table_name(),
			$data,
			array(
				'id' => $order_id,
			),
			$formats,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Restore an order to the status it had before the last KDS action.
	 *
	 * @param int $order_id Order ID.
	 * @return string|false Restored status on success.
	 */
	public static function undo_last_status_change( $order_id ) {
		$order = self::get_order( $order_id );

		if ( ! $order || ! in_array( $order['status'], array( 'paid', 'completed', 'canceled' ), true ) ) {
			return false;
		}

		$target_status = in_array( $order['previous_status'], array( 'pending', 'paid' ), true ) ? $order['previous_status'] : '';

		if ( '' === $target_status ) {
			$target_status = $order['payment_method'] || $order['paid_at'] ? 'paid' : 'pending';
		}

		$args = array();
		if ( 'pending' === $target_status && self::is_expired( $order ) ) {
			$timeout = absint( Laca_KDS_Settings::get( 'payment_timeout_seconds', 180 ) );
			if ( $timeout > 0 ) {
				$args['expires_at'] = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $timeout );
			}
		}

		return self::update_status( $order_id, $target_status, $args ) ? $target_status : false;
	}

	/**
	 * Cancel pending orders whose payment window has expired.
	 *
	 * @param int $limit Maximum orders to cancel per pass.
	 * @return int Number of affected rows.
	 */
	public static function cancel_expired_pending_orders( $limit = 100 ) {
		global $wpdb;

		$limit = min( 500, max( 1, absint( $limit ) ) );
		$now   = current_time( 'mysql' );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . laca_kds_orders_table_name() . " SET status = 'canceled', previous_status = 'pending', updated_at = %s WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at <= %s LIMIT %d",
				$now,
				$now,
				$limit
			)
		);

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Check if an order's payment window has expired.
	 *
	 * @param array $order Formatted order.
	 * @return bool
	 */
	public static function is_expired( $order ) {
		if ( empty( $order['expires_at'] ) ) {
			return false;
		}

		return strtotime( $order['expires_at'] ) <= current_time( 'timestamp' );
	}

	/**
	 * Seconds remaining before payment expiry.
	 *
	 * @param array $order Formatted order.
	 * @return int
	 */
	public static function seconds_until_expiry( $order ) {
		if ( empty( $order['expires_at'] ) ) {
			return 0;
		}

		return max( 0, strtotime( $order['expires_at'] ) - current_time( 'timestamp' ) );
	}

	/**
	 * Revenue summary for paid/completed orders in a date range.
	 *
	 * @param string $from_mysql Start datetime.
	 * @param string $to_mysql End datetime.
	 * @return array
	 */
	public static function get_revenue_summary( $from_mysql, $to_mysql ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS revenue FROM " . laca_kds_orders_table_name() . " WHERE status IN ('paid', 'completed') AND paid_at IS NOT NULL AND paid_at BETWEEN %s AND %s",
				$from_mysql,
				$to_mysql
			),
			ARRAY_A
		);

		return array(
			'order_count' => isset( $row['order_count'] ) ? (int) $row['order_count'] : 0,
			'revenue'     => isset( $row['revenue'] ) ? (float) $row['revenue'] : 0,
		);
	}

	/**
	 * Daily revenue rows for the admin dashboard.
	 *
	 * @param int $days Number of days.
	 * @return array
	 */
	public static function get_daily_revenue( $days = 14 ) {
		global $wpdb;

		$days      = min( 60, max( 1, absint( $days ) ) );
		$from_date = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - ( ( $days - 1 ) * DAY_IN_SECONDS ) );
		$rows      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(paid_at) AS revenue_date, COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS revenue FROM " . laca_kds_orders_table_name() . " WHERE status IN ('paid', 'completed') AND paid_at IS NOT NULL AND paid_at >= %s GROUP BY revenue_date ORDER BY revenue_date DESC",
				$from_date
			),
			ARRAY_A
		);

		return $rows ? array_map(
			static function ( $row ) {
				return array(
					'date'        => sanitize_text_field( $row['revenue_date'] ),
					'order_count' => (int) $row['order_count'],
					'revenue'     => (float) $row['revenue'],
				);
			},
			$rows
		) : array();
	}

	/**
	 * Daily revenue rows for a custom date range.
	 *
	 * @param string $from_mysql Start datetime.
	 * @param string $to_mysql End datetime.
	 * @return array
	 */
	public static function get_daily_revenue_between( $from_mysql, $to_mysql ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(paid_at) AS revenue_date, COUNT(*) AS order_count, COALESCE(SUM(total_amount), 0) AS revenue FROM " . laca_kds_orders_table_name() . " WHERE status IN ('paid', 'completed') AND paid_at IS NOT NULL AND paid_at BETWEEN %s AND %s GROUP BY revenue_date ORDER BY revenue_date DESC",
				$from_mysql,
				$to_mysql
			),
			ARRAY_A
		);

		return $rows ? array_map(
			static function ( $row ) {
				return array(
					'date'        => sanitize_text_field( $row['revenue_date'] ),
					'order_count' => (int) $row['order_count'],
					'revenue'     => (float) $row['revenue'],
				);
			},
			$rows
		) : array();
	}

	/**
	 * Fetch paid/completed orders in a revenue date range for detailed reporting.
	 *
	 * @param string $from_mysql Start datetime.
	 * @param string $to_mysql End datetime.
	 * @param int    $limit Maximum orders.
	 * @return array
	 */
	public static function get_revenue_orders( $from_mysql, $to_mysql, $limit = 200 ) {
		global $wpdb;

		$limit = min( 1000, max( 1, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *, paid_at AS revenue_at FROM " . laca_kds_orders_table_name() . " WHERE status IN ('paid', 'completed') AND paid_at IS NOT NULL AND paid_at BETWEEN %s AND %s ORDER BY revenue_at DESC, id DESC LIMIT %d",
				$from_mysql,
				$to_mysql,
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				$formatted               = self::format_order_row( $row );
				$formatted['revenue_at'] = isset( $row['revenue_at'] ) ? sanitize_text_field( $row['revenue_at'] ) : '';

				return $formatted;
			},
			$rows
		);
	}

	/**
	 * Aggregate sold items from paid/completed orders in a revenue date range.
	 *
	 * @param string $from_mysql Start datetime.
	 * @param string $to_mysql End datetime.
	 * @return array
	 */
	public static function get_revenue_item_sales( $from_mysql, $to_mysql ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_items FROM " . laca_kds_orders_table_name() . " WHERE status IN ('paid', 'completed') AND paid_at IS NOT NULL AND paid_at BETWEEN %s AND %s ORDER BY paid_at DESC",
				$from_mysql,
				$to_mysql
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			$order_items = json_decode( (string) $row['order_items'], true );
			if ( ! is_array( $order_items ) ) {
				continue;
			}

			foreach ( $order_items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$item_type = isset( $item['item_type'] ) ? sanitize_key( $item['item_type'] ) : 'food';
				if ( ! in_array( $item_type, array( 'food', 'combo' ), true ) ) {
					continue;
				}

				$food_id    = isset( $item['food_id'] ) ? absint( $item['food_id'] ) : 0;
				$combo_id   = isset( $item['combo_id'] ) ? absint( $item['combo_id'] ) : 0;
				$name       = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
				$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
				$price      = isset( $item['price'] ) ? (float) $item['price'] : 0;
				$line_total = isset( $item['line_total'] ) ? (float) $item['line_total'] : $price * $quantity;
				$key        = 'combo' === $item_type && $combo_id ? 'combo-' . $combo_id : ( $food_id ? 'food-' . $food_id : 'name-' . sanitize_title( $name ) );

				if ( '' === $name ) {
					$name = $combo_id ? sprintf( 'Combo #%d', $combo_id ) : ( $food_id ? sprintf( 'Món #%d', $food_id ) : __( 'Món không rõ', 'laca-self-ordering-kds' ) );
				}

				if ( ! isset( $items[ $key ] ) ) {
					$items[ $key ] = array(
						'food_id'     => $food_id,
						'combo_id'    => $combo_id,
						'item_type'   => $item_type,
						'name'        => $name,
						'quantity'    => 0,
						'revenue'     => 0,
						'order_count' => 0,
					);
				}

				$items[ $key ]['quantity'] += $quantity;
				$items[ $key ]['revenue'] += $line_total;
				$items[ $key ]['order_count']++;
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['revenue'] === $b['revenue'] ) {
					return $b['quantity'] <=> $a['quantity'];
				}

				return $b['revenue'] <=> $a['revenue'];
			}
		);

		return array_values( $items );
	}

	/**
	 * Store a payment webhook/manual payment event for reconciliation.
	 *
	 * @param array $data Log data.
	 * @return int|false
	 */
	public static function log_payment_event( $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			laca_kds_payment_logs_table_name(),
			array(
				'order_id'     => isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0,
				'provider'     => isset( $data['provider'] ) ? sanitize_key( $data['provider'] ) : '',
				'amount'       => isset( $data['amount'] ) ? (float) $data['amount'] : 0,
				'content'      => isset( $data['content'] ) ? sanitize_textarea_field( $data['content'] ) : '',
				'status'       => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : '',
				'payload_json' => isset( $data['payload_json'] ) ? (string) $data['payload_json'] : '',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Fetch recent payment events for reconciliation.
	 *
	 * @param int $limit Maximum rows.
	 * @return array
	 */
	public static function get_payment_logs( $limit = 100 ) {
		global $wpdb;

		$limit = min( 300, max( 1, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . laca_kds_payment_logs_table_name() . ' ORDER BY created_at DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				return array(
					'id'         => (int) $row['id'],
					'order_id'   => (int) $row['order_id'],
					'provider'   => sanitize_key( $row['provider'] ),
					'amount'     => (float) $row['amount'],
					'content'    => sanitize_textarea_field( $row['content'] ),
					'status'     => sanitize_key( $row['status'] ),
					'created_at' => sanitize_text_field( $row['created_at'] ),
				);
			},
			$rows
		);
	}

	/**
	 * Store an SMS/ZNS notification attempt.
	 *
	 * @param array $data Log data.
	 * @return int|false
	 */
	public static function log_notification_event( $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			laca_kds_notification_logs_table_name(),
			array(
				'order_id'                 => isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0,
				'customer_phone'           => isset( $data['customer_phone'] ) ? sanitize_text_field( $data['customer_phone'] ) : '',
				'provider'                 => isset( $data['provider'] ) ? sanitize_key( $data['provider'] ) : '',
				'message'                  => isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : '',
				'status'                   => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : '',
				'http_code'                => isset( $data['http_code'] ) ? absint( $data['http_code'] ) : 0,
				'provider_code'            => isset( $data['provider_code'] ) ? sanitize_text_field( $data['provider_code'] ) : '',
				'provider_transaction_id'  => isset( $data['provider_transaction_id'] ) ? sanitize_text_field( $data['provider_transaction_id'] ) : '',
				'request_json'             => isset( $data['request_json'] ) ? (string) $data['request_json'] : '',
				'response_body'            => isset( $data['response_body'] ) ? (string) $data['response_body'] : '',
				'error_message'            => isset( $data['error_message'] ) ? sanitize_textarea_field( $data['error_message'] ) : '',
				'created_at'               => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Save or refresh a browser push subscription for one order.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $public_token Public order token.
	 * @param array  $subscription Push subscription JSON.
	 * @return int|false
	 */
	public static function upsert_push_subscription( $order_id, $public_token, $subscription ) {
		global $wpdb;

		$order_id     = absint( $order_id );
		$public_token = sanitize_text_field( $public_token );
		$endpoint     = isset( $subscription['endpoint'] ) ? esc_url_raw( $subscription['endpoint'] ) : '';
		$keys         = isset( $subscription['keys'] ) && is_array( $subscription['keys'] ) ? $subscription['keys'] : array();
		$p256dh       = isset( $keys['p256dh'] ) ? sanitize_text_field( $keys['p256dh'] ) : '';
		$auth         = isset( $keys['auth'] ) ? sanitize_text_field( $keys['auth'] ) : '';

		if ( ! $order_id || '' === $public_token || '' === $endpoint ) {
			return false;
		}

		$now           = current_time( 'mysql' );
		$endpoint_hash = hash( 'sha256', $endpoint );
		$user_agent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$existing_id   = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . laca_kds_push_subscriptions_table_name() . ' WHERE endpoint_hash = %s',
				$endpoint_hash
			)
		);

		$data = array(
			'order_id'      => $order_id,
			'public_token'  => $public_token,
			'endpoint_hash' => $endpoint_hash,
			'endpoint'      => $endpoint,
			'p256dh'        => $p256dh,
			'auth'          => $auth,
			'user_agent'    => $user_agent,
			'status'        => 'active',
			'updated_at'    => $now,
		);

		if ( $existing_id ) {
			$updated = $wpdb->update(
				laca_kds_push_subscriptions_table_name(),
				$data,
				array( 'id' => $existing_id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return false === $updated ? false : $existing_id;
		}

		$data['created_at'] = $now;
		$inserted           = $wpdb->insert(
			laca_kds_push_subscriptions_table_name(),
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Get active web push subscriptions for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_push_subscriptions_for_order( $order_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . laca_kds_push_subscriptions_table_name() . " WHERE order_id = %d AND status = 'active' ORDER BY updated_at DESC LIMIT 10",
				absint( $order_id )
			),
			ARRAY_A
		);

		return $rows ? array_map(
			static function ( $row ) {
				return array(
					'id'       => (int) $row['id'],
					'endpoint' => esc_url_raw( $row['endpoint'] ),
					'p256dh'   => sanitize_text_field( $row['p256dh'] ),
					'auth'     => sanitize_text_field( $row['auth'] ),
				);
			},
			$rows
		) : array();
	}

	/**
	 * Mark one push subscription inactive.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function deactivate_push_subscription( $subscription_id ) {
		global $wpdb;

		$wpdb->update(
			laca_kds_push_subscriptions_table_name(),
			array(
				'status'     => 'inactive',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $subscription_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fetch recent SMS/ZNS notification logs.
	 *
	 * @param int $limit Maximum rows.
	 * @return array
	 */
	public static function get_notification_logs( $limit = 100 ) {
		global $wpdb;

		$limit = min( 300, max( 1, absint( $limit ) ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . laca_kds_notification_logs_table_name() . ' ORDER BY created_at DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				return array(
					'id'                       => (int) $row['id'],
					'order_id'                 => (int) $row['order_id'],
					'customer_phone'           => sanitize_text_field( $row['customer_phone'] ),
					'provider'                 => sanitize_key( $row['provider'] ),
					'message'                  => sanitize_textarea_field( $row['message'] ),
					'status'                   => sanitize_key( $row['status'] ),
					'http_code'                => (int) $row['http_code'],
					'provider_code'            => sanitize_text_field( $row['provider_code'] ),
					'provider_transaction_id'  => sanitize_text_field( $row['provider_transaction_id'] ),
					'request_json'             => isset( $row['request_json'] ) ? sanitize_textarea_field( $row['request_json'] ) : '',
					'response_body'            => sanitize_textarea_field( $row['response_body'] ),
					'error_message'            => sanitize_textarea_field( $row['error_message'] ),
					'created_at'               => sanitize_text_field( $row['created_at'] ),
				);
			},
			$rows
		);
	}

	/**
	 * Mask a phone number for public display.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	public static function mask_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );

		if ( strlen( $digits ) < 6 ) {
			return '***';
		}

		return substr( $digits, 0, 2 ) . '****' . substr( $digits, -3 );
	}

	/**
	 * Decode and normalize a DB row.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function format_order_row( $row ) {
		$order_items = json_decode( (string) $row['order_items'], true );
		if ( ! is_array( $order_items ) ) {
			$order_items = array();
		}

		return array(
			'id'             => (int) $row['id'],
			'customer_phone' => sanitize_text_field( $row['customer_phone'] ),
			'total_amount'   => (float) $row['total_amount'],
			'order_items'    => $order_items,
			'status'         => sanitize_key( $row['status'] ),
			'previous_status' => isset( $row['previous_status'] ) ? sanitize_key( $row['previous_status'] ) : '',
			'payment_method' => isset( $row['payment_method'] ) ? sanitize_key( $row['payment_method'] ) : '',
			'public_token'   => isset( $row['public_token'] ) ? sanitize_text_field( $row['public_token'] ) : '',
			'masked_phone'   => self::mask_phone( $row['customer_phone'] ),
			'expires_at'     => isset( $row['expires_at'] ) ? sanitize_text_field( $row['expires_at'] ) : '',
			'paid_at'        => isset( $row['paid_at'] ) ? sanitize_text_field( $row['paid_at'] ) : '',
			'created_at'     => sanitize_text_field( $row['created_at'] ),
			'updated_at'     => isset( $row['updated_at'] ) ? sanitize_text_field( $row['updated_at'] ) : '',
			'completed_at'   => isset( $row['completed_at'] ) ? sanitize_text_field( $row['completed_at'] ) : '',
		);
	}
}
