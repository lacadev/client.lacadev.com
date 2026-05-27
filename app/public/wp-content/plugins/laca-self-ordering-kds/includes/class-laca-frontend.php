<?php
/**
 * Frontend shortcode app.
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the customer ordering interface.
 */
class Laca_KDS_Frontend {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'laca_menu_app', array( __CLASS__, 'render_menu_app_shortcode' ) );
		add_shortcode( 'laca_order_status', array( __CLASS__, 'render_order_status_shortcode' ) );
		add_shortcode( 'laca_pickup_screen', array( __CLASS__, 'render_pickup_screen_shortcode' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_pwa_head_tags' ) );
	}

	/**
	 * Add PWA metadata on public plugin pages.
	 *
	 * @return void
	 */
	public static function render_pwa_head_tags() {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$template = get_page_template_slug( get_queried_object_id() );
		if ( ! in_array( $template, array( Laca_KDS_Settings::MENU_PAGE_TEMPLATE, Laca_KDS_Settings::STATUS_PAGE_TEMPLATE, Laca_KDS_Settings::PICKUP_PAGE_TEMPLATE ), true ) ) {
			return;
		}

		$settings = Laca_KDS_Settings::get_settings();
		?>
		<link rel="manifest" href="<?php echo esc_url( rest_url( 'laca/v1/pwa-manifest' ) ); ?>" />
		<meta name="theme-color" content="<?php echo esc_attr( $settings['theme_accent'] ); ?>" />
		<meta name="apple-mobile-web-app-capable" content="yes" />
		<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $settings['stall_name'] ); ?>" />
		<?php
	}

	/**
	 * Render [laca_menu_app].
	 *
	 * @return string
	 */
	public static function render_menu_app_shortcode() {
		self::enqueue_assets();

		$menu_items = Laca_KDS_REST_API::get_cached_menu_items_array();
		$categories = get_terms(
			array(
				'taxonomy'   => Laca_KDS_Food_CPT::TAXONOMY,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		$categories = is_wp_error( $categories ) ? array() : $categories;
		$settings   = Laca_KDS_Settings::get_settings();

		ob_start();
		?>
		<div class="laca-menu-app" data-currency="VND" style="<?php echo esc_attr( laca_kds_get_theme_style_attr() ); ?>">
			<header class="laca-menu-hero">
				<p class="laca-menu-eyebrow"><?php echo esc_html( $settings['stall_name'] ); ?></p>
				<h2><?php echo esc_html( $settings['stall_tagline'] ); ?></h2>
				<p><?php echo esc_html( $settings['stall_description'] ); ?></p>
			</header>

			<section class="laca-menu-section" data-laca-step="menu">
				<div class="laca-menu-toolbar" aria-label="<?php esc_attr_e( 'Tìm và lọc món', 'laca-self-ordering-kds' ); ?>">
					<label class="laca-search-box" for="laca_food_search">
						<span><?php esc_html_e( 'Tìm món nhanh', 'laca-self-ordering-kds' ); ?></span>
						<input type="search" id="laca_food_search" class="laca-food-search" placeholder="<?php esc_attr_e( 'Nhập tên món...', 'laca-self-ordering-kds' ); ?>" autocomplete="off" />
					</label>

					<div class="laca-category-filter" role="list" aria-label="<?php esc_attr_e( 'Lọc theo phân loại', 'laca-self-ordering-kds' ); ?>">
						<button type="button" class="laca-category-chip is-active" data-category="all" role="listitem">
							<?php esc_html_e( 'Tất cả', 'laca-self-ordering-kds' ); ?>
						</button>
						<?php foreach ( $categories as $category ) : ?>
							<button type="button" class="laca-category-chip" data-category="<?php echo esc_attr( $category->slug ); ?>" role="listitem">
								<?php echo esc_html( $category->name ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<div class="laca-menu-results-status" aria-live="polite"></div>
				</div>

				<?php if ( ! empty( $menu_items ) ) : ?>
					<div class="laca-food-grid">
						<?php foreach ( $menu_items as $item ) : ?>
							<?php
							$item_id       = absint( $item['item_id'] );
							$item_type     = sanitize_key( $item['item_type'] );
							$price         = (float) $item['price'];
							$regular_price = (float) $item['regular_price'];
							$thumbnail     = $item['thumbnail_url'];
							$item_terms    = isset( $item['categories'] ) && is_array( $item['categories'] ) ? $item['categories'] : array();
							$is_combo      = ! empty( $item['is_combo'] );
							$variants      = isset( $item['variants'] ) && is_array( $item['variants'] ) ? $item['variants'] : array();
							?>
							<article
								class="laca-food-card"
								data-item-id="<?php echo esc_attr( $item_id ); ?>"
								data-item-type="<?php echo esc_attr( $item_type ); ?>"
								data-food-id="<?php echo esc_attr( $item['food_id'] ); ?>"
								data-combo-id="<?php echo esc_attr( $item['combo_id'] ); ?>"
								data-food-name="<?php echo esc_attr( $item['name'] ); ?>"
								data-food-price="<?php echo esc_attr( $price ); ?>"
								data-regular-price="<?php echo esc_attr( $regular_price ); ?>"
								data-variants="<?php echo esc_attr( wp_json_encode( $variants ) ); ?>"
							>
								<div class="laca-food-card__image">
									<?php if ( $thumbnail ) : ?>
										<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" loading="lazy" />
									<?php else : ?>
										<span><?php esc_html_e( 'La Cà', 'laca-self-ordering-kds' ); ?></span>
									<?php endif; ?>
								</div>
								<div class="laca-food-card__body">
									<?php if ( $is_combo ) : ?>
										<span class="laca-combo-badge"><?php echo esc_html( $item['combo_badge'] ); ?></span>
									<?php endif; ?>
									<h3><?php echo esc_html( $item['name'] ); ?></h3>
									<p class="laca-food-card__price">
										<?php if ( $is_combo && $regular_price > $price ) : ?>
											<del><?php echo esc_html( number_format_i18n( $regular_price ) ); ?>đ</del>
										<?php endif; ?>
										<strong><?php echo esc_html( number_format_i18n( $price ) ); ?>đ</strong>
									</p>
									<?php if ( $is_combo && ! empty( $item['combo_details'] ) ) : ?>
										<p class="laca-combo-details"><?php echo esc_html( $item['combo_details'] ); ?></p>
									<?php endif; ?>
									<?php if ( ! empty( $item_terms ) ) : ?>
										<div class="laca-food-card__terms">
											<?php foreach ( $item_terms as $item_term ) : ?>
												<span><?php echo esc_html( $item_term['name'] ); ?></span>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
									<?php if ( ! $is_combo && ! empty( $variants ) ) : ?>
										<div class="laca-variant-picker">
											<?php foreach ( $variants as $group_index => $group ) : ?>
												<label>
													<span><?php echo esc_html( $group['name'] ); ?></span>
													<select class="laca-variant-select" data-group-index="<?php echo esc_attr( $group_index ); ?>">
														<?php foreach ( $group['options'] as $option_index => $option ) : ?>
															<option value="<?php echo esc_attr( $option_index ); ?>" data-price-delta="<?php echo esc_attr( $option['price_delta'] ); ?>">
																<?php echo esc_html( $option['name'] ); ?>
																<?php if ( (float) $option['price_delta'] > 0 ) : ?>
																	+<?php echo esc_html( number_format_i18n( (float) $option['price_delta'] ) ); ?>đ
																<?php endif; ?>
															</option>
														<?php endforeach; ?>
													</select>
												</label>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
									<div class="laca-food-card__actions">
										<div class="laca-quantity-control" data-cart-key="<?php echo esc_attr( $item_type . ':' . $item_id ); ?>" aria-label="<?php esc_attr_e( 'Chọn số lượng', 'laca-self-ordering-kds' ); ?>">
											<button type="button" class="laca-quantity-btn laca-quantity-minus" data-direction="-1" aria-label="<?php esc_attr_e( 'Giảm số lượng', 'laca-self-ordering-kds' ); ?>">-</button>
											<strong class="laca-card-quantity">0</strong>
											<button type="button" class="laca-quantity-btn laca-quantity-plus" data-direction="1" aria-label="<?php esc_attr_e( 'Tăng số lượng', 'laca-self-ordering-kds' ); ?>">+</button>
										</div>
										<button type="button" class="laca-add-to-cart">
											<span aria-hidden="true">+</span>
											<?php esc_html_e( 'Thêm', 'laca-self-ordering-kds' ); ?>
										</button>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="laca-empty-menu">
						<?php esc_html_e( 'Menu chưa có món. Vui lòng thêm món trong Foods.', 'laca-self-ordering-kds' ); ?>
					</div>
				<?php endif; ?>
			</section>

			<aside class="laca-floating-cart" hidden>
				<div class="laca-cart-head">
					<strong><?php esc_html_e( 'Giỏ món', 'laca-self-ordering-kds' ); ?></strong>
					<span class="laca-cart-count">0</span>
				</div>
				<div class="laca-cart-items"></div>
				<div class="laca-cart-total"></div>
				<button type="button" class="laca-checkout-button"><?php esc_html_e( 'Thanh toán', 'laca-self-ordering-kds' ); ?></button>
			</aside>

			<section class="laca-checkout-panel" data-laca-step="checkout" hidden>
				<h3><?php esc_html_e( 'Thông tin nhận món', 'laca-self-ordering-kds' ); ?></h3>
				<form class="laca-checkout-form">
					<label for="laca_customer_phone"><?php esc_html_e( 'Số điện thoại', 'laca-self-ordering-kds' ); ?></label>
					<input type="tel" id="laca_customer_phone" name="customer_phone" required placeholder="0901234567" autocomplete="tel" />
					<div class="laca-checkout-summary"></div>
					<div class="laca-form-message" role="alert"></div>
					<button type="submit"><?php esc_html_e( 'Tạo đơn và lấy mã QR', 'laca-self-ordering-kds' ); ?></button>
					<button type="button" class="laca-back-to-menu"><?php esc_html_e( 'Chọn thêm món', 'laca-self-ordering-kds' ); ?></button>
				</form>
			</section>

			<section class="laca-payment-panel" data-laca-step="payment" hidden>
				<div class="laca-payment-card">
					<p class="laca-payment-kicker"><?php esc_html_e( 'Bước cuối', 'laca-self-ordering-kds' ); ?></p>
					<h3><?php esc_html_e( 'Quét VietQR để thanh toán', 'laca-self-ordering-kds' ); ?></h3>
					<div class="laca-qr-wrap">
						<img class="laca-qr-image" src="" alt="<?php esc_attr_e( 'VietQR payment code', 'laca-self-ordering-kds' ); ?>" />
					</div>
					<div class="laca-payment-countdown" hidden></div>
					<div class="laca-payment-details"></div>
					<div class="laca-payment-actions"></div>
					<p class="laca-status-link-wrap"></p>
					<p class="laca-payment-note"><?php esc_html_e( 'Trên điện thoại, bạn có thể mở QR lớn rồi lưu/quét từ ảnh trong app ngân hàng, hoặc sao chép nội dung chuyển khoản để nhập thủ công. Sau khi ngân hàng xác nhận, bếp sẽ nhận đơn.', 'laca-self-ordering-kds' ); ?></p>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the public order status page.
	 *
	 * @return string
	 */
	public static function render_order_status_shortcode() {
		self::enqueue_status_assets();

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$settings = Laca_KDS_Settings::get_settings();

		ob_start();
		?>
		<div class="laca-menu-app laca-status-app" style="<?php echo esc_attr( laca_kds_get_theme_style_attr() ); ?>" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-token="<?php echo esc_attr( $token ); ?>">
			<header class="laca-menu-hero">
				<p class="laca-menu-eyebrow"><?php echo esc_html( $settings['stall_name'] ); ?></p>
				<h2><?php esc_html_e( 'Theo dõi đơn', 'laca-self-ordering-kds' ); ?></h2>
				<p><?php esc_html_e( 'Trang này tự cập nhật để bạn biết khi nào đến quầy nhận món.', 'laca-self-ordering-kds' ); ?></p>
			</header>
			<div class="laca-status-notify-callout" hidden>
				<div>
					<strong><?php esc_html_e( 'Muốn nhận báo khi món xong?', 'laca-self-ordering-kds' ); ?></strong>
					<span><?php esc_html_e( 'Giữ trang này mở, plugin sẽ rung/hiện thông báo khi quầy bấm Hoàn thành.', 'laca-self-ordering-kds' ); ?></span>
				</div>
				<button type="button" class="laca-status-enable-notify"><?php esc_html_e( 'Bật thông báo', 'laca-self-ordering-kds' ); ?></button>
			</div>
			<section class="laca-status-card" aria-live="polite">
				<div class="laca-status-loading"><?php esc_html_e( 'Đang tải trạng thái đơn...', 'laca-self-ordering-kds' ); ?></div>
			</section>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a public pickup display for completed orders.
	 *
	 * @return string
	 */
	public static function render_pickup_screen_shortcode() {
		self::enqueue_pickup_assets();

		$settings = Laca_KDS_Settings::get_settings();

		ob_start();
		?>
		<div class="laca-menu-app laca-pickup-app" style="<?php echo esc_attr( laca_kds_get_theme_style_attr() ); ?>">
			<header class="laca-menu-hero">
				<p class="laca-menu-eyebrow"><?php echo esc_html( $settings['stall_name'] ); ?></p>
				<h2><?php esc_html_e( 'Món đã sẵn sàng', 'laca-self-ordering-kds' ); ?></h2>
				<p><?php esc_html_e( 'Nếu thấy mã đơn của bạn, vui lòng đến quầy nhận món nhé.', 'laca-self-ordering-kds' ); ?></p>
			</header>
			<section class="laca-pickup-board" aria-live="polite">
				<div class="laca-status-loading"><?php esc_html_e( 'Đang tải danh sách...', 'laca-self-ordering-kds' ); ?></div>
			</section>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Enqueue frontend assets for the shortcode.
	 *
	 * @return void
	 */
	private static function enqueue_assets() {
		$settings = Laca_KDS_Settings::get_settings();

		wp_enqueue_style(
			'laca-menu-app',
			LACA_KDS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			LACA_KDS_VERSION
		);
		laca_kds_enqueue_quicksand_font( 'laca-menu-app' );

		wp_enqueue_script(
			'laca-menu-app',
			LACA_KDS_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			LACA_KDS_VERSION,
			true
		);

		wp_localize_script(
			'laca-menu-app',
			'lacaMenuApp',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'laca/v1/orders' ) ),
				'menuItemsUrl' => esc_url_raw( rest_url( 'laca/v1/menu-items' ) ),
				'statusUrl'    => esc_url_raw( rest_url( 'laca/v1/orders/status' ) ),
				'statusPageUrl' => esc_url_raw( Laca_KDS_Settings::get_status_page_url() ),
				'nonce'        => wp_create_nonce( 'laca_create_order_nonce' ),
				'promotions'    => array(
					'rules' => Laca_KDS_Settings::get_promotion_rules( $settings ),
				),
				'i18n'         => array(
					'emptyCart'       => __( 'Vui lòng chọn ít nhất một món.', 'laca-self-ordering-kds' ),
					'invalidPhone'    => __( 'Vui lòng nhập số điện thoại hợp lệ.', 'laca-self-ordering-kds' ),
					'creatingOrder'   => __( 'Đang tạo đơn...', 'laca-self-ordering-kds' ),
					'genericError'    => __( 'Có lỗi xảy ra. Vui lòng thử lại.', 'laca-self-ordering-kds' ),
					'searching'       => __( 'Đang tìm món...', 'laca-self-ordering-kds' ),
					'noResults'       => __( 'Không tìm thấy món phù hợp.', 'laca-self-ordering-kds' ),
					'resultCount'     => __( 'món phù hợp', 'laca-self-ordering-kds' ),
					'addToCart'       => __( 'Thêm món', 'laca-self-ordering-kds' ),
					'cartTotal'       => __( 'Tổng cộng', 'laca-self-ordering-kds' ),
					'orderId'         => __( 'Mã đơn', 'laca-self-ordering-kds' ),
					'transferContent' => __( 'Nội dung chuyển khoản', 'laca-self-ordering-kds' ),
					'amount'          => __( 'Số tiền', 'laca-self-ordering-kds' ),
					'bank'            => __( 'Ngân hàng', 'laca-self-ordering-kds' ),
					'account'         => __( 'Tài khoản', 'laca-self-ordering-kds' ),
					'trackOrder'      => __( 'Theo dõi trạng thái đơn', 'laca-self-ordering-kds' ),
					'openQr'          => __( 'Mở QR lớn', 'laca-self-ordering-kds' ),
					'copyTransfer'    => __( 'Sao chép nội dung CK', 'laca-self-ordering-kds' ),
					'copied'          => __( 'Đã sao chép', 'laca-self-ordering-kds' ),
					'paymentCountdown' => __( 'Vui lòng thanh toán trong', 'laca-self-ordering-kds' ),
					'paymentExpired'  => __( 'Mã QR đã hết hạn. Nếu bạn chưa thanh toán, vui lòng tạo đơn mới.', 'laca-self-ordering-kds' ),
					'paymentConfirmed' => __( 'Đã nhận thanh toán. Bếp đang chuẩn bị món cho bạn.', 'laca-self-ordering-kds' ),
				),
			)
		);
	}

	/**
	 * Enqueue order status assets.
	 *
	 * @return void
	 */
	private static function enqueue_status_assets() {
		$settings = Laca_KDS_Settings::get_settings();

		wp_enqueue_style( 'laca-menu-app', LACA_KDS_PLUGIN_URL . 'assets/css/frontend.css', array(), LACA_KDS_VERSION );
		laca_kds_enqueue_quicksand_font( 'laca-menu-app' );
		wp_enqueue_script( 'laca-order-status', LACA_KDS_PLUGIN_URL . 'assets/js/order-status.js', array( 'jquery' ), LACA_KDS_VERSION, true );
		wp_localize_script(
			'laca-order-status',
			'lacaOrderStatus',
			array(
				'statusUrl'         => esc_url_raw( rest_url( 'laca/v1/orders/status' ) ),
				'pushSubscribeUrl'  => esc_url_raw( rest_url( 'laca/v1/push-subscription' ) ),
				'serviceWorkerUrl'  => esc_url_raw( LACA_KDS_PLUGIN_URL . 'assets/js/laca-push-sw.js' ),
				'vapidPublicKey'    => ! empty( $settings['web_push_enabled'] ) ? sanitize_text_field( $settings['web_push_public_key'] ) : '',
				'refreshIntervalMs' => min( 30000, max( 5000, absint( $settings['order_status_refresh_interval_ms'] ) ) ),
				'i18n'              => array(
					'notFound'          => __( 'Không tìm thấy đơn hàng.', 'laca-self-ordering-kds' ),
					'pending'           => __( 'Chờ thanh toán', 'laca-self-ordering-kds' ),
					'paid'              => __( 'Bếp đã nhận đơn', 'laca-self-ordering-kds' ),
					'completed'         => __( 'Món đã sẵn sàng', 'laca-self-ordering-kds' ),
					'canceled'          => __( 'Đơn đã hủy', 'laca-self-ordering-kds' ),
					'queueNumber' => __( 'Mã nhận món', 'laca-self-ordering-kds' ),
					'readyTitle'        => __( 'Món của bạn đã sẵn sàng', 'laca-self-ordering-kds' ),
					'readyBody'         => __( 'Vui lòng đến quầy nhận món nhé!', 'laca-self-ordering-kds' ),
					'notifyEnabled'     => __( 'Đã bật thông báo. Hãy giữ trang này mở trong lúc chờ món.', 'laca-self-ordering-kds' ),
					'notifyUnsupported' => __( 'Trình duyệt này chưa hỗ trợ thông báo. Trang vẫn tự cập nhật và rung khi món xong nếu được hỗ trợ.', 'laca-self-ordering-kds' ),
				),
			)
		);
	}

	/**
	 * Enqueue pickup display assets.
	 *
	 * @return void
	 */
	private static function enqueue_pickup_assets() {
		wp_enqueue_style( 'laca-menu-app', LACA_KDS_PLUGIN_URL . 'assets/css/frontend.css', array(), LACA_KDS_VERSION );
		laca_kds_enqueue_quicksand_font( 'laca-menu-app' );
		wp_enqueue_script( 'laca-pickup-screen', LACA_KDS_PLUGIN_URL . 'assets/js/pickup-screen.js', array( 'jquery' ), LACA_KDS_VERSION, true );
		wp_localize_script(
			'laca-pickup-screen',
			'lacaPickupScreen',
			array(
				'pickupUrl' => esc_url_raw( rest_url( 'laca/v1/pickup-orders' ) ),
				'i18n'      => array(
					'empty' => __( 'Chưa có đơn nào sẵn sàng.', 'laca-self-ordering-kds' ),
				),
			)
		);
	}
}
