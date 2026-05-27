<?php
/**
 * Admin Kitchen Display System.
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KDS manager admin page and AJAX handlers.
 */
class Laca_KDS_Admin_KDS {
	const MENU_SLUG = 'laca-kds-manager';
	const QUICK_MENU_SLUG = 'laca-kds-quick-menu';
	const PROMOTIONS_SLUG = 'laca-kds-promotions';
	const PAYMENT_LOGS_SLUG = 'laca-kds-payment-logs';
	const REVENUE_SLUG = 'laca-kds-revenue';
	const NOTIFICATION_LOGS_SLUG = 'laca-kds-notification-logs';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_menu', array( __CLASS__, 'reorder_admin_submenu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_laca_kds_get_orders', array( __CLASS__, 'ajax_get_orders' ) );
		add_action( 'wp_ajax_laca_kds_update_status', array( __CLASS__, 'ajax_update_status' ) );
		add_action( 'wp_ajax_laca_kds_get_food_items', array( __CLASS__, 'ajax_get_food_items' ) );
		add_action( 'wp_ajax_laca_kds_toggle_food_availability', array( __CLASS__, 'ajax_toggle_food_availability' ) );
	}

	/**
	 * Add the KDS manager menu page.
	 *
	 * @return void
	 */
	public static function register_admin_menu() {
		add_menu_page(
			__( 'KDS Manager', 'laca-self-ordering-kds' ),
			__( 'KDS Manager', 'laca-self-ordering-kds' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_admin_page' ),
			'dashicons-layout',
			26
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Promotions', 'laca-self-ordering-kds' ),
			__( 'Khuyến mãi', 'laca-self-ordering-kds' ),
			'manage_options',
			self::PROMOTIONS_SLUG,
			array( 'Laca_KDS_Settings', 'render_promotions_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Revenue', 'laca-self-ordering-kds' ),
			__( 'Doanh thu', 'laca-self-ordering-kds' ),
			'manage_options',
			self::REVENUE_SLUG,
			array( __CLASS__, 'render_revenue_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Notification Logs', 'laca-self-ordering-kds' ),
			__( 'Tin nhắn', 'laca-self-ordering-kds' ),
			'manage_options',
			self::NOTIFICATION_LOGS_SLUG,
			array( __CLASS__, 'render_notification_logs_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Payment Reconciliation', 'laca-self-ordering-kds' ),
			__( 'Đối soát', 'laca-self-ordering-kds' ),
			'manage_options',
			self::PAYMENT_LOGS_SLUG,
			array( __CLASS__, 'render_payment_logs_page' )
		);
	}

	/**
	 * Keep the KDS submenu ordered around the seller's daily workflow.
	 *
	 * @return void
	 */
	public static function reorder_admin_submenu() {
		global $submenu;

		if ( empty( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		$food_slug       = 'edit.php?post_type=' . ( class_exists( 'Laca_KDS_Food_CPT' ) ? Laca_KDS_Food_CPT::POST_TYPE : 'laca_food' );
		$category_slug   = 'edit-tags.php?taxonomy=' . ( class_exists( 'Laca_KDS_Food_CPT' ) ? Laca_KDS_Food_CPT::TAXONOMY : 'laca_food_category' ) . '&post_type=' . ( class_exists( 'Laca_KDS_Food_CPT' ) ? Laca_KDS_Food_CPT::POST_TYPE : 'laca_food' );
		$combo_slug      = 'edit.php?post_type=' . ( class_exists( 'Laca_KDS_Combo_CPT' ) ? Laca_KDS_Combo_CPT::POST_TYPE : 'laca_combo' );
		$settings_slug   = class_exists( 'Laca_KDS_Settings' ) ? Laca_KDS_Settings::SETTINGS_PAGE_SLUG : 'laca-kds-settings';
		$preferred_order = array(
			$food_slug,
			$category_slug,
			$combo_slug,
			self::PROMOTIONS_SLUG,
			self::MENU_SLUG,
			self::REVENUE_SLUG,
			self::NOTIFICATION_LOGS_SLUG,
			self::PAYMENT_LOGS_SLUG,
			$settings_slug,
		);

		$items = array();
		foreach ( $submenu[ self::MENU_SLUG ] as $item ) {
			if ( isset( $item[2] ) && self::QUICK_MENU_SLUG === $item[2] ) {
				continue;
			}
			if ( isset( $item[2] ) && self::MENU_SLUG === $item[2] ) {
				$item[0] = __( 'Quản lý đơn', 'laca-self-ordering-kds' );
			}
			$items[] = $item;
		}

		$deduped = array();
		foreach ( $items as $item ) {
			$slug = isset( $item[2] ) ? (string) $item[2] : '';
			if ( '' === $slug || isset( $deduped[ $slug ] ) ) {
				continue;
			}
			$deduped[ $slug ] = $item;
		}

		$ordered = array();
		foreach ( $preferred_order as $slug ) {
			if ( isset( $deduped[ $slug ] ) ) {
				$ordered[] = $deduped[ $slug ];
				unset( $deduped[ $slug ] );
			}
		}

		$submenu[ self::MENU_SLUG ] = array_merge( $ordered, array_values( $deduped ) );
	}

	/**
	 * Enqueue KDS assets only on this page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$is_kds_page      = 'toplevel_page_' . self::MENU_SLUG === $hook_suffix;
		$is_quick_menu    = false !== strpos( $hook_suffix, self::QUICK_MENU_SLUG );
		$is_revenue_page  = false !== strpos( $hook_suffix, self::REVENUE_SLUG );
		$is_sms_logs      = false !== strpos( $hook_suffix, self::NOTIFICATION_LOGS_SLUG );
		$is_payment_logs  = false !== strpos( $hook_suffix, self::PAYMENT_LOGS_SLUG );
		$is_settings_page = class_exists( 'Laca_KDS_Settings' ) && false !== strpos( $hook_suffix, Laca_KDS_Settings::SETTINGS_PAGE_SLUG );
		$is_promotions_page = false !== strpos( $hook_suffix, self::PROMOTIONS_SLUG );

		if ( ! $is_kds_page && ! $is_settings_page && ! $is_promotions_page && ! $is_quick_menu && ! $is_revenue_page && ! $is_sms_logs && ! $is_payment_logs ) {
			return;
		}

		wp_enqueue_style(
			'laca-kds-admin',
			LACA_KDS_PLUGIN_URL . 'assets/css/admin-kds.css',
			array(),
			LACA_KDS_VERSION
		);
		laca_kds_enqueue_quicksand_font( 'laca-kds-admin' );

		if ( $is_settings_page || $is_promotions_page ) {
			wp_enqueue_script(
				'laca-kds-settings',
				LACA_KDS_PLUGIN_URL . 'assets/js/settings.js',
				array( 'jquery' ),
				LACA_KDS_VERSION,
				true
			);
			wp_localize_script(
				'laca-kds-settings',
				'lacaKdsSettings',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'laca_kds_settings_nonce' ),
					'i18n'    => array(
						'testing' => __( 'Đang gửi SMS test...', 'laca-self-ordering-kds' ),
						'success' => __( 'Đã gửi request test.', 'laca-self-ordering-kds' ),
						'error'   => __( 'Không gửi được SMS test.', 'laca-self-ordering-kds' ),
					),
				)
			);
		}

		if ( ! $is_kds_page && ! $is_quick_menu ) {
			return;
		}

		wp_enqueue_script(
			'laca-kds-admin',
			LACA_KDS_PLUGIN_URL . 'assets/js/admin-kds.js',
			array( 'jquery' ),
			LACA_KDS_VERSION,
			true
		);

		wp_localize_script(
			'laca-kds-admin',
			'lacaKdsAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'laca_kds_admin_nonce' ),
				'refreshIntervalMs' => absint( Laca_KDS_Settings::get( 'kds_refresh_interval_ms', 2000 ) ),
				'i18n'              => array(
					'loading'       => __( 'Đang tải đơn...', 'laca-self-ordering-kds' ),
					'empty'         => __( 'Hiện chưa có đơn chờ xử lý.', 'laca-self-ordering-kds' ),
					'emptyRecent'   => __( 'Chưa có đơn vừa xử lý.', 'laca-self-ordering-kds' ),
					'error'         => __( 'Không tải được đơn. Vui lòng thử lại.', 'laca-self-ordering-kds' ),
					'updating'      => __( 'Đang cập nhật...', 'laca-self-ordering-kds' ),
					'completed'     => __( 'Hoàn thành', 'laca-self-ordering-kds' ),
					'paid'          => __( 'Xác nhận TT', 'laca-self-ordering-kds' ),
					'canceled'      => __( 'Hủy', 'laca-self-ordering-kds' ),
					'undo'          => __( 'Hoàn tác', 'laca-self-ordering-kds' ),
					'available'     => __( 'Còn món', 'laca-self-ordering-kds' ),
					'soldOut'       => __( 'Hết món', 'laca-self-ordering-kds' ),
					'lastUpdated'   => __( 'Cập nhật lúc', 'laca-self-ordering-kds' ),
					'confirmCancel' => __( 'Hủy đơn này?', 'laca-self-ordering-kds' ),
					'confirmUndo'   => __( 'Hoàn tác trạng thái đơn này?', 'laca-self-ordering-kds' ),
				),
			)
		);
	}

	/**
	 * Render the KDS page.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'laca-self-ordering-kds' ) );
		}
		?>
		<div class="wrap laca-kds-wrap">
			<div class="laca-kds-header">
				<div>
					<h1><?php esc_html_e( 'Quản lý đơn hàng', 'laca-self-ordering-kds' ); ?></h1>
					<p>
						<?php
						printf(
							/* translators: %s: refresh interval in seconds. */
							esc_html__( 'Cập nhật gần realtime mỗi %s giây. Đơn chờ thanh toán và đã thanh toán sẽ hiện ở đây cho đến khi hoàn thành hoặc hủy.', 'laca-self-ordering-kds' ),
							esc_html( number_format_i18n( absint( Laca_KDS_Settings::get( 'kds_refresh_interval_ms', 2000 ) ) / 1000, 1 ) )
						);
						?>
					</p>
				</div>
				<button type="button" class="button button-primary" id="laca-kds-refresh">
					<?php esc_html_e( 'Làm mới', 'laca-self-ordering-kds' ); ?>
				</button>
			</div>

			<div class="laca-kds-status-bar laca-kds-status-bar--compact">
				<span id="laca-kds-last-updated"><?php esc_html_e( 'Đang chờ tải đơn...', 'laca-self-ordering-kds' ); ?></span>
			</div>

			<div id="laca-kds-summary" class="laca-kds-summary" aria-live="polite"></div>

			<div class="laca-kds-rule-note">
				<span><?php esc_html_e( 'Lưu ý vận hành', 'laca-self-ordering-kds' ); ?></span>
				<p><?php esc_html_e( 'Đơn chờ thanh toán nếu bấm Hoàn thành sẽ không gửi SMS và không tính doanh thu. Đơn đã thanh toán khi hoàn thành sẽ gửi SMS và được tính vào doanh thu.', 'laca-self-ordering-kds' ); ?></p>
			</div>

			<div id="laca-kds-board" class="laca-kds-board" aria-live="polite">
				<div class="laca-kds-loading"><?php esc_html_e( 'Đang tải đơn...', 'laca-self-ordering-kds' ); ?></div>
			</div>

			<div class="laca-kds-recent">
				<h2><?php esc_html_e( 'Vừa xử lý', 'laca-self-ordering-kds' ); ?></h2>
				<p><?php esc_html_e( 'Nếu bấm nhầm Hoàn thành hoặc Hủy, dùng nút Hoàn tác ở đây để đưa đơn về trạng thái trước đó.', 'laca-self-ordering-kds' ); ?></p>
				<div id="laca-kds-recent-board" class="laca-kds-board laca-kds-board--recent" aria-live="polite"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render quick menu availability page.
	 *
	 * @return void
	 */
	public static function render_quick_menu_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'laca-self-ordering-kds' ) );
		}
		?>
		<div class="wrap laca-kds-wrap">
			<div class="laca-kds-header">
				<div>
					<h1><?php esc_html_e( 'Menu nhanh', 'laca-self-ordering-kds' ); ?></h1>
					<p><?php esc_html_e( 'Bật/tắt món hết hàng ngay trong lúc bán. Món hết sẽ tự ẩn khỏi menu khách.', 'laca-self-ordering-kds' ); ?></p>
				</div>
				<button type="button" class="button button-primary" id="laca-food-refresh">
					<?php esc_html_e( 'Refresh', 'laca-self-ordering-kds' ); ?>
				</button>
			</div>
			<div id="laca-food-board" class="laca-food-admin-board" aria-live="polite">
				<div class="laca-kds-loading"><?php esc_html_e( 'Loading menu...', 'laca-self-ordering-kds' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render revenue dashboard.
	 *
	 * @return void
	 */
	public static function render_revenue_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'laca-self-ordering-kds' ) );
		}

		$today_date   = current_time( 'Y-m-d' );
		$default_from = gmdate( 'Y-m-d', current_time( 'timestamp' ) - ( 6 * DAY_IN_SECONDS ) );
		$default_to   = $today_date;
		$from_date    = isset( $_GET['laca_from'] ) ? self::sanitize_report_date( wp_unslash( $_GET['laca_from'] ), $default_from ) : $default_from;
		$to_date      = isset( $_GET['laca_to'] ) ? self::sanitize_report_date( wp_unslash( $_GET['laca_to'] ), $default_to ) : $default_to;

		if ( strtotime( $from_date ) > strtotime( $to_date ) ) {
			$temp_date = $from_date;
			$from_date = $to_date;
			$to_date   = $temp_date;
		}

		$from_mysql    = $from_date . ' 00:00:00';
		$to_mysql      = $to_date . ' 23:59:59';
		$today_start   = $today_date . ' 00:00:00';
		$today_end     = $today_date . ' 23:59:59';
		$week_start    = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - ( 6 * DAY_IN_SECONDS ) );
		$week_end      = $today_end;
		$today         = Laca_KDS_Orders_Repository::get_revenue_summary( $today_start, $today_end );
		$week          = Laca_KDS_Orders_Repository::get_revenue_summary( $week_start, $week_end );
		$range_summary = Laca_KDS_Orders_Repository::get_revenue_summary( $from_mysql, $to_mysql );
		$daily_rows    = Laca_KDS_Orders_Repository::get_daily_revenue_between( $from_mysql, $to_mysql );
		$item_sales    = Laca_KDS_Orders_Repository::get_revenue_item_sales( $from_mysql, $to_mysql );
		$orders        = Laca_KDS_Orders_Repository::get_revenue_orders( $from_mysql, $to_mysql, 300 );
		$total_items   = array_sum( wp_list_pluck( $item_sales, 'quantity' ) );
		$average_order = $range_summary['order_count'] > 0 ? $range_summary['revenue'] / $range_summary['order_count'] : 0;
		?>
		<div class="wrap laca-kds-wrap">
			<div class="laca-kds-header">
				<div>
					<h1><?php esc_html_e( 'Doanh thu', 'laca-self-ordering-kds' ); ?></h1>
					<p><?php esc_html_e( 'Chỉ tính các đơn có xác nhận thanh toán. Đơn chờ thanh toán được bấm Hoàn thành kiểu tiền mặt sẽ không tính doanh thu trên web.', 'laca-self-ordering-kds' ); ?></p>
				</div>
			</div>

			<form class="laca-revenue-filter" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::REVENUE_SLUG ); ?>" />
				<label>
					<span><?php esc_html_e( 'Từ ngày', 'laca-self-ordering-kds' ); ?></span>
					<input type="date" name="laca_from" value="<?php echo esc_attr( $from_date ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Đến ngày', 'laca-self-ordering-kds' ); ?></span>
					<input type="date" name="laca_to" value="<?php echo esc_attr( $to_date ); ?>" />
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Lọc doanh thu', 'laca-self-ordering-kds' ); ?></button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::REVENUE_SLUG ) ); ?>"><?php esc_html_e( '7 ngày gần nhất', 'laca-self-ordering-kds' ); ?></a>
			</form>

			<div class="laca-revenue-grid laca-revenue-grid--detail">
				<div class="laca-revenue-card">
					<span><?php esc_html_e( 'Hôm nay', 'laca-self-ordering-kds' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $today['revenue'] ) ); ?>đ</strong>
					<em><?php echo esc_html( number_format_i18n( $today['order_count'] ) ); ?> <?php esc_html_e( 'đơn', 'laca-self-ordering-kds' ); ?></em>
				</div>
				<div class="laca-revenue-card">
					<span><?php esc_html_e( '7 ngày gần nhất', 'laca-self-ordering-kds' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $week['revenue'] ) ); ?>đ</strong>
					<em><?php echo esc_html( number_format_i18n( $week['order_count'] ) ); ?> <?php esc_html_e( 'đơn', 'laca-self-ordering-kds' ); ?></em>
				</div>
				<div class="laca-revenue-card laca-revenue-card--accent">
					<span><?php esc_html_e( 'Khoảng đang lọc', 'laca-self-ordering-kds' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $range_summary['revenue'] ) ); ?>đ</strong>
					<em><?php echo esc_html( number_format_i18n( $range_summary['order_count'] ) ); ?> <?php esc_html_e( 'đơn', 'laca-self-ordering-kds' ); ?> · <?php esc_html_e( 'TB', 'laca-self-ordering-kds' ); ?> <?php echo esc_html( number_format_i18n( $average_order ) ); ?>đ</em>
				</div>
				<div class="laca-revenue-card">
					<span><?php esc_html_e( 'Món đã bán', 'laca-self-ordering-kds' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $total_items ) ); ?></strong>
					<em><?php echo esc_html( number_format_i18n( count( $item_sales ) ) ); ?> <?php esc_html_e( 'loại món', 'laca-self-ordering-kds' ); ?></em>
				</div>
			</div>

			<div class="laca-revenue-detail-grid">
				<section class="laca-payment-log-table laca-revenue-table">
					<h2><?php esc_html_e( 'Theo ngày', 'laca-self-ordering-kds' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Ngày', 'laca-self-ordering-kds' ); ?></th>
								<th><?php esc_html_e( 'Số đơn', 'laca-self-ordering-kds' ); ?></th>
								<th><?php esc_html_e( 'Doanh thu', 'laca-self-ordering-kds' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $daily_rows ) ) : ?>
								<tr>
									<td colspan="3"><?php esc_html_e( 'Chưa có doanh thu trong khoảng này.', 'laca-self-ordering-kds' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $daily_rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['date'] ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['order_count'] ) ); ?></td>
										<td><strong><?php echo esc_html( number_format_i18n( $row['revenue'] ) ); ?>đ</strong></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</section>

				<section class="laca-payment-log-table laca-revenue-table">
					<h2><?php esc_html_e( 'Món bán chạy', 'laca-self-ordering-kds' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Món', 'laca-self-ordering-kds' ); ?></th>
								<th><?php esc_html_e( 'SL', 'laca-self-ordering-kds' ); ?></th>
								<th><?php esc_html_e( 'Số đơn', 'laca-self-ordering-kds' ); ?></th>
								<th><?php esc_html_e( 'Doanh thu', 'laca-self-ordering-kds' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $item_sales ) ) : ?>
								<tr>
									<td colspan="4"><?php esc_html_e( 'Chưa có món nào được bán trong khoảng này.', 'laca-self-ordering-kds' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $item_sales as $item ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $item['name'] ); ?></strong></td>
										<td><?php echo esc_html( number_format_i18n( $item['quantity'] ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $item['order_count'] ) ); ?></td>
										<td><strong><?php echo esc_html( number_format_i18n( $item['revenue'] ) ); ?>đ</strong></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</section>
			</div>

			<div class="laca-payment-log-table laca-revenue-table laca-revenue-orders">
				<div class="laca-revenue-section-head">
					<div>
						<h2><?php esc_html_e( 'Chi tiết đơn hàng', 'laca-self-ordering-kds' ); ?></h2>
						<p><?php esc_html_e( 'Hiển thị tối đa 300 đơn gần nhất trong khoảng lọc.', 'laca-self-ordering-kds' ); ?></p>
					</div>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Đơn', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Thời gian', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'SĐT', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Món', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Trạng thái', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Thanh toán', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Tổng', 'laca-self-ordering-kds' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $orders ) ) : ?>
							<tr>
								<td colspan="7"><?php esc_html_e( 'Chưa có đơn đã thanh toán trong khoảng này.', 'laca-self-ordering-kds' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $orders as $order ) : ?>
								<tr>
									<td><strong>#<?php echo esc_html( $order['id'] ); ?></strong></td>
									<td>
										<?php echo esc_html( $order['revenue_at'] ); ?>
										<?php if ( $order['completed_at'] ) : ?>
											<small><?php esc_html_e( 'Hoàn:', 'laca-self-ordering-kds' ); ?> <?php echo esc_html( $order['completed_at'] ); ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $order['customer_phone'] ); ?></td>
									<td><?php self::render_revenue_order_items( $order['order_items'] ); ?></td>
									<td><span class="laca-status-badge laca-status-<?php echo esc_attr( $order['status'] ); ?>"><?php echo esc_html( self::get_order_status_label( $order['status'] ) ); ?></span></td>
									<td><?php echo $order['payment_method'] ? esc_html( $order['payment_method'] ) : '-'; ?></td>
									<td><strong><?php echo esc_html( number_format_i18n( $order['total_amount'] ) ); ?>đ</strong></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize a YYYY-MM-DD report date.
	 *
	 * @param string $value Raw date.
	 * @param string $fallback Fallback date.
	 * @return string
	 */
	private static function sanitize_report_date( $value, $fallback ) {
		$value = sanitize_text_field( (string) $value );

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : $fallback;
	}

	/**
	 * Render a compact order item list for the revenue table.
	 *
	 * @param array $items Order items.
	 * @return void
	 */
	private static function render_revenue_order_items( $items ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			echo esc_html__( 'Không có món', 'laca-self-ordering-kds' );
			return;
		}
		?>
		<ul class="laca-revenue-order-items">
			<?php foreach ( $items as $item ) : ?>
				<?php
				if ( ! is_array( $item ) ) {
					continue;
				}

				$name       = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : __( 'Món không rõ', 'laca-self-ordering-kds' );
				$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
				$line_total = isset( $item['line_total'] ) ? (float) $item['line_total'] : ( isset( $item['price'] ) ? (float) $item['price'] * $quantity : 0 );
				$item_type  = isset( $item['item_type'] ) ? sanitize_key( $item['item_type'] ) : 'food';
				$variants   = isset( $item['variants'] ) && is_array( $item['variants'] ) ? $item['variants'] : array();
				?>
				<li class="laca-revenue-order-item--<?php echo esc_attr( $item_type ); ?>">
					<span>
						<?php if ( 'discount' === $item_type ) : ?>
							<?php esc_html_e( 'Giảm:', 'laca-self-ordering-kds' ); ?> <?php echo esc_html( $name ); ?>
						<?php elseif ( 'gift' === $item_type ) : ?>
							<?php esc_html_e( 'Tặng:', 'laca-self-ordering-kds' ); ?> <?php echo esc_html( $name ); ?>
						<?php elseif ( 'combo' === $item_type ) : ?>
							<?php echo esc_html( $quantity ); ?>x <?php esc_html_e( 'Combo:', 'laca-self-ordering-kds' ); ?> <?php echo esc_html( $name ); ?>
						<?php else : ?>
							<?php echo esc_html( $quantity ); ?>x <?php echo esc_html( $name ); ?>
						<?php endif; ?>
						<?php if ( ! empty( $variants ) ) : ?>
							<small>
								<?php
								echo esc_html(
									implode(
										' · ',
										array_map(
											static function ( $variant ) {
												$group  = isset( $variant['group'] ) ? sanitize_text_field( $variant['group'] ) : '';
												$option = isset( $variant['option'] ) ? sanitize_text_field( $variant['option'] ) : '';

												return trim( $group . ': ' . $option, ': ' );
											},
											$variants
										)
									)
								);
								?>
							</small>
						<?php endif; ?>
					</span>
					<strong><?php echo esc_html( number_format_i18n( $line_total ) ); ?>đ</strong>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Human-readable order status labels.
	 *
	 * @param string $status Order status.
	 * @return string
	 */
	private static function get_order_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Chờ thanh toán', 'laca-self-ordering-kds' ),
			'paid'      => __( 'Đã thanh toán', 'laca-self-ordering-kds' ),
			'completed' => __( 'Hoàn thành', 'laca-self-ordering-kds' ),
			'canceled'  => __( 'Đã hủy', 'laca-self-ordering-kds' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Render notification logs.
	 *
	 * @return void
	 */
	public static function render_notification_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'laca-self-ordering-kds' ) );
		}

		$logs = Laca_KDS_Orders_Repository::get_notification_logs( 150 );
		?>
		<div class="wrap laca-kds-wrap">
			<div class="laca-kds-header">
				<div>
					<h1><?php esc_html_e( 'Tin nhắn đã gửi', 'laca-self-ordering-kds' ); ?></h1>
					<p><?php esc_html_e( 'Theo dõi SMS/Zalo ZNS được kích hoạt khi admin bấm Hoàn thành trong KDS.', 'laca-self-ordering-kds' ); ?></p>
				</div>
			</div>
			<div class="laca-setup-guide">
				<h3><?php esc_html_e( 'Cách đọc log', 'laca-self-ordering-kds' ); ?></h3>
				<p><?php esc_html_e( 'sent = nhà cung cấp đã nhận request thành công. error = token sai, hết tiền, sai sms_type/sender, số điện thoại không hợp lệ hoặc provider trả lỗi. Với SpeedSMS, plugin tự đổi số 09... thành 849... trước khi gửi.', 'laca-self-ordering-kds' ); ?></p>
			</div>
			<div class="laca-payment-log-table">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Thời gian', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Đơn', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Số nhận', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Nguồn', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Trạng thái', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Mã', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Request', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Nội dung', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Phản hồi', 'laca-self-ordering-kds' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $logs ) ) : ?>
							<tr>
								<td colspan="9"><?php esc_html_e( 'Chưa có log tin nhắn. Log sẽ xuất hiện sau khi bấm Hoàn thành một đơn mới hoặc gửi SMS test.', 'laca-self-ordering-kds' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log['created_at'] ); ?></td>
									<td><?php echo $log['order_id'] ? '#' . esc_html( $log['order_id'] ) : '-'; ?></td>
									<td><?php echo esc_html( $log['customer_phone'] ); ?></td>
									<td><?php echo esc_html( $log['provider'] ); ?></td>
									<td><span class="laca-status-badge laca-log-<?php echo esc_attr( $log['status'] ); ?>"><?php echo esc_html( $log['status'] ); ?></span></td>
									<td>
										<?php if ( $log['http_code'] ) : ?>
											<code>HTTP <?php echo esc_html( $log['http_code'] ); ?></code>
										<?php endif; ?>
										<?php if ( $log['provider_code'] ) : ?>
											<code><?php echo esc_html( $log['provider_code'] ); ?></code>
										<?php endif; ?>
										<?php if ( $log['provider_transaction_id'] ) : ?>
											<code><?php echo esc_html( $log['provider_transaction_id'] ); ?></code>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! empty( $log['request_json'] ) ) : ?>
											<code><?php echo esc_html( wp_trim_words( $log['request_json'], 24 ) ); ?></code>
										<?php else : ?>
											-
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $log['message'] ); ?></td>
									<td>
										<?php if ( $log['error_message'] ) : ?>
											<code><?php echo esc_html( wp_trim_words( $log['error_message'], 24 ) ); ?></code>
										<?php elseif ( $log['response_body'] ) : ?>
											<code><?php echo esc_html( wp_trim_words( $log['response_body'], 24 ) ); ?></code>
										<?php else : ?>
											-
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render payment reconciliation logs.
	 *
	 * @return void
	 */
	public static function render_payment_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'laca-self-ordering-kds' ) );
		}

		$logs = Laca_KDS_Orders_Repository::get_payment_logs( 150 );
		?>
		<div class="wrap laca-kds-wrap">
			<div class="laca-kds-header">
				<div>
					<h1><?php esc_html_e( 'Đối soát thanh toán', 'laca-self-ordering-kds' ); ?></h1>
					<p><?php esc_html_e( 'Theo dõi webhook ngân hàng và xác nhận thanh toán thủ công gần nhất.', 'laca-self-ordering-kds' ); ?></p>
				</div>
			</div>
			<div class="laca-payment-log-table">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Thời gian', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Đơn', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Nguồn', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Số tiền', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Nội dung', 'laca-self-ordering-kds' ); ?></th>
							<th><?php esc_html_e( 'Trạng thái', 'laca-self-ordering-kds' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $logs ) ) : ?>
							<tr>
								<td colspan="6"><?php esc_html_e( 'Chưa có log thanh toán.', 'laca-self-ordering-kds' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td><?php echo esc_html( $log['created_at'] ); ?></td>
									<td><?php echo $log['order_id'] ? '#' . esc_html( $log['order_id'] ) : '-'; ?></td>
									<td><?php echo esc_html( $log['provider'] ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $log['amount'] ) ); ?>đ</td>
									<td><?php echo esc_html( $log['content'] ); ?></td>
									<td><span class="laca-status-badge laca-log-<?php echo esc_attr( $log['status'] ); ?>"><?php echo esc_html( $log['status'] ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Return open KDS orders.
	 *
	 * @return void
	 */
	public static function ajax_get_orders() {
		self::verify_ajax_request();

		$orders        = array_map( array( __CLASS__, 'format_kds_order' ), Laca_KDS_Orders_Repository::get_open_orders() );
		$recent_orders = array_map( array( __CLASS__, 'format_kds_order' ), Laca_KDS_Orders_Repository::get_recent_closed_orders() );

		wp_send_json_success(
			array(
				'orders'        => $orders,
				'recent_orders' => $recent_orders,
			)
		);
	}

	/**
	 * Update an order status from the KDS.
	 *
	 * @return void
	 */
	public static function ajax_update_status() {
		self::verify_ajax_request();

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $order_id || ! in_array( $status, array( 'paid', 'completed', 'canceled', 'undo' ), true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid order update.', 'laca-self-ordering-kds' ) ),
				400
			);
		}

		$order = Laca_KDS_Orders_Repository::get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error(
				array( 'message' => __( 'Order not found.', 'laca-self-ordering-kds' ) ),
				404
			);
		}

		$previous_status = $order['status'];
		if ( 'undo' === $status ) {
			$restored_status = Laca_KDS_Orders_Repository::undo_last_status_change( $order_id );
			if ( ! $restored_status ) {
				wp_send_json_error(
					array( 'message' => __( 'Could not undo this order.', 'laca-self-ordering-kds' ) ),
					500
				);
			}

			wp_send_json_success(
				array(
					'order_id' => $order_id,
					'status'   => $restored_status,
				)
			);
		}

		$args    = 'paid' === $status ? array( 'payment_method' => 'manual' ) : array();
		$updated = Laca_KDS_Orders_Repository::update_status( $order_id, $status, $args );

		if ( ! $updated ) {
			wp_send_json_error(
				array( 'message' => __( 'Could not update order.', 'laca-self-ordering-kds' ) ),
				500
			);
		}

		if ( 'completed' === $status && 'paid' === $previous_status ) {
			laca_send_notification( $order_id );
			laca_send_web_push_notification( $order_id );
		}

		if ( 'paid' === $status && 'paid' !== $previous_status ) {
			Laca_KDS_Orders_Repository::log_payment_event(
				array(
					'order_id'     => $order_id,
					'provider'     => 'manual',
					'amount'       => $order['total_amount'],
					'content'      => 'Manual payment confirmation from KDS',
					'status'       => 'matched',
					'payload_json' => wp_json_encode( array( 'source' => 'kds_admin' ) ),
				)
			);
		}

		wp_send_json_success(
			array(
				'order_id' => $order_id,
				'status'   => $status,
			)
		);
	}

	/**
	 * Return food items for quick availability controls.
	 *
	 * @return void
	 */
	public static function ajax_get_food_items() {
		self::verify_ajax_request();

		$query = new WP_Query(
			array(
				'post_type'      => Laca_KDS_Food_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$items = array();

		while ( $query->have_posts() ) {
			$query->the_post();
			$food_id = get_the_ID();
			$items[] = array(
				'id'           => $food_id,
				'name'         => get_the_title(),
				'price'        => Laca_KDS_Food_CPT::get_price( $food_id ),
				'is_available' => Laca_KDS_Food_CPT::is_available( $food_id ),
				'edit_url'     => get_edit_post_link( $food_id, 'raw' ),
			);
		}
		wp_reset_postdata();

		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * Toggle one food item's availability.
	 *
	 * @return void
	 */
	public static function ajax_toggle_food_availability() {
		self::verify_ajax_request();

		$food_id      = isset( $_POST['food_id'] ) ? absint( $_POST['food_id'] ) : 0;
		$is_available = isset( $_POST['is_available'] ) ? absint( $_POST['is_available'] ) : 0;

		if ( ! $food_id || Laca_KDS_Food_CPT::POST_TYPE !== get_post_type( $food_id ) || ! current_user_can( 'edit_post', $food_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid food item.', 'laca-self-ordering-kds' ) ), 400 );
		}

		update_post_meta( $food_id, Laca_KDS_Food_CPT::AVAILABLE_META, $is_available ? '1' : '0' );

		if ( class_exists( 'Laca_KDS_REST_API' ) ) {
			Laca_KDS_REST_API::flush_menu_cache();
		}

		wp_send_json_success(
			array(
				'food_id'      => $food_id,
				'is_available' => (bool) $is_available,
			)
		);
	}

	/**
	 * Common AJAX permission and nonce checks.
	 *
	 * @return void
	 */
	private static function verify_ajax_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'laca-self-ordering-kds' ) ),
				403
			);
		}

		check_ajax_referer( 'laca_kds_admin_nonce', 'nonce' );
	}

	/**
	 * Add transient UI fields for the KDS board.
	 *
	 * @param array $order Formatted order row.
	 * @return array
	 */
	private static function format_kds_order( $order ) {
		$order['expires_in'] = Laca_KDS_Orders_Repository::seconds_until_expiry( $order );

		return $order;
	}
}
