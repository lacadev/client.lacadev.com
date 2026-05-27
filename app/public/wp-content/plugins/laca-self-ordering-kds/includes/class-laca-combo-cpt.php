<?php
/**
 * Combo/set custom post type.
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers combos built from existing food items.
 */
class Laca_KDS_Combo_CPT {
	const POST_TYPE      = 'laca_combo';
	const PRICE_META     = '_laca_combo_price';
	const AVAILABLE_META = '_laca_combo_available';
	const ITEMS_META     = '_laca_combo_items';
	const BADGE_META     = '_laca_combo_badge';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
	}

	/**
	 * Register the combo CPT.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Combos', 'laca-self-ordering-kds' ),
			'singular_name'      => __( 'Combo', 'laca-self-ordering-kds' ),
			'menu_name'          => __( 'Combo / Set món', 'laca-self-ordering-kds' ),
			'add_new'            => __( 'Thêm combo', 'laca-self-ordering-kds' ),
			'add_new_item'       => __( 'Thêm combo mới', 'laca-self-ordering-kds' ),
			'edit_item'          => __( 'Sửa combo', 'laca-self-ordering-kds' ),
			'new_item'           => __( 'Combo mới', 'laca-self-ordering-kds' ),
			'view_item'          => __( 'Xem combo', 'laca-self-ordering-kds' ),
			'search_items'       => __( 'Tìm combo', 'laca-self-ordering-kds' ),
			'not_found'          => __( 'Chưa có combo.', 'laca-self-ordering-kds' ),
			'not_found_in_trash' => __( 'Không có combo trong thùng rác.', 'laca-self-ordering-kds' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => class_exists( 'Laca_KDS_Admin_KDS' ) ? Laca_KDS_Admin_KDS::MENU_SLUG : true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-tickets-alt',
				'supports'           => array( 'title', 'thumbnail' ),
				'taxonomies'         => array( Laca_KDS_Food_CPT::TAXONOMY ),
				'has_archive'        => false,
				'rewrite'            => array( 'slug' => 'laca-combo' ),
				'capability_type'    => 'post',
				'publicly_queryable' => false,
			)
		);
	}

	/**
	 * Add combo configuration box.
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'laca_combo_builder',
			__( 'Cấu hình combo', 'laca-self-ordering-kds' ),
			array( __CLASS__, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue combo admin helpers.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'laca-kds-admin',
			LACA_KDS_PLUGIN_URL . 'assets/css/admin-kds.css',
			array(),
			LACA_KDS_VERSION
		);
		laca_kds_enqueue_quicksand_font( 'laca-kds-admin' );

		if ( 'edit.php' === $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'laca-kds-combo-admin',
			LACA_KDS_PLUGIN_URL . 'assets/js/combo-admin.js',
			array( 'jquery' ),
			LACA_KDS_VERSION,
			true
		);
	}

	/**
	 * Render combo settings.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		$price        = self::get_price( $post->ID );
		$is_available = '0' !== get_post_meta( $post->ID, self::AVAILABLE_META, true );
		$badge        = self::get_badge( $post->ID );
		$items        = self::get_raw_items( $post->ID );
		$foods        = self::get_food_options();
		$rows         = ! empty( $items ) ? $items : array(
			array(
				'food_id'  => 0,
				'quantity' => 1,
			),
		);

		wp_nonce_field( 'laca_combo_nonce_action', 'laca_combo_nonce' );
		?>
		<div class="laca-combo-builder">
			<div class="laca-combo-builder__hero">
				<div>
					<span><?php esc_html_e( 'Combo builder', 'laca-self-ordering-kds' ); ?></span>
					<h3><?php esc_html_e( 'Ghép món lẻ thành set bán nhanh', 'laca-self-ordering-kds' ); ?></h3>
					<p><?php esc_html_e( 'Mỗi món chỉ chọn một lần trong combo. Nếu cần nhiều phần cùng món, tăng số lượng ở dòng đó.', 'laca-self-ordering-kds' ); ?></p>
				</div>
				<div class="laca-combo-price-preview">
					<span><?php esc_html_e( 'Giá gốc hiện tại', 'laca-self-ordering-kds' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( self::get_original_price( $post->ID ) ) ); ?>đ</strong>
				</div>
			</div>

			<div class="laca-combo-builder__summary">
				<label>
					<?php esc_html_e( 'Giá combo bán ra', 'laca-self-ordering-kds' ); ?>
					<input type="number" min="0" step="any" name="laca_combo_price" value="<?php echo esc_attr( $price ); ?>" />
				</label>
				<label>
					<?php esc_html_e( 'Nhãn hiển thị', 'laca-self-ordering-kds' ); ?>
					<input type="text" name="laca_combo_badge" value="<?php echo esc_attr( $badge ); ?>" placeholder="<?php esc_attr_e( 'Combo tiết kiệm', 'laca-self-ordering-kds' ); ?>" />
				</label>
				<label class="laca-food-inline-switch">
					<input type="checkbox" name="laca_combo_available" value="1" <?php checked( $is_available ); ?> />
					<span><?php esc_html_e( 'Đang bán combo này', 'laca-self-ordering-kds' ); ?></span>
				</label>
			</div>

			<div class="laca-combo-items" data-next-index="<?php echo esc_attr( count( $rows ) ); ?>">
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php self::render_combo_item_row( $foods, $index, absint( $row['food_id'] ), absint( $row['quantity'] ) ); ?>
				<?php endforeach; ?>
			</div>
			<p class="laca-combo-unique-warning" hidden><?php esc_html_e( 'Món này đã có trong combo. Hãy tăng số lượng ở dòng cũ thay vì chọn trùng.', 'laca-self-ordering-kds' ); ?></p>

			<button type="button" class="button laca-combo-add-row"><?php esc_html_e( 'Thêm món vào combo', 'laca-self-ordering-kds' ); ?></button>

			<template id="laca-combo-row-template">
				<?php self::render_combo_item_row( $foods, '__index__', 0, 1 ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * Render one combo item row.
	 *
	 * @param array      $foods Food options.
	 * @param int|string $index Row index.
	 * @param int        $selected_food Selected food ID.
	 * @param int        $quantity Quantity.
	 * @return void
	 */
	private static function render_combo_item_row( $foods, $index, $selected_food, $quantity ) {
		?>
		<div class="laca-combo-item-row">
			<span class="laca-combo-item-row__badge" aria-hidden="true">
				<?php echo esc_html( is_numeric( $index ) ? '#' . ( (int) $index + 1 ) : '#' ); ?>
			</span>
			<div class="laca-combo-item-row__main">
				<label>
					<?php esc_html_e( 'Món lẻ', 'laca-self-ordering-kds' ); ?>
					<select name="laca_combo_items[<?php echo esc_attr( $index ); ?>][food_id]">
						<option value="0"><?php esc_html_e( 'Chọn món...', 'laca-self-ordering-kds' ); ?></option>
						<?php foreach ( $foods as $food ) : ?>
							<option value="<?php echo esc_attr( $food['id'] ); ?>" data-price="<?php echo esc_attr( $food['price'] ); ?>" <?php selected( $selected_food, $food['id'] ); ?>>
								<?php echo esc_html( $food['name'] . ' - ' . number_format_i18n( $food['price'] ) . 'đ' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<label>
				<?php esc_html_e( 'SL', 'laca-self-ordering-kds' ); ?>
				<input type="number" min="1" max="99" name="laca_combo_items[<?php echo esc_attr( $index ); ?>][quantity]" value="<?php echo esc_attr( max( 1, $quantity ) ); ?>" />
			</label>
			<button type="button" class="button laca-combo-remove-row"><?php esc_html_e( 'Xóa', 'laca-self-ordering-kds' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Save combo meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['laca_combo_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['laca_combo_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'laca_combo_nonce_action' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( self::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$price = isset( $_POST['laca_combo_price'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['laca_combo_price'] ) ) : 0;
		$badge = isset( $_POST['laca_combo_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['laca_combo_badge'] ) ) : '';
		$items = isset( $_POST['laca_combo_items'] ) && is_array( $_POST['laca_combo_items'] ) ? wp_unslash( $_POST['laca_combo_items'] ) : array();

		update_post_meta( $post_id, self::PRICE_META, max( 0, $price ) );
		update_post_meta( $post_id, self::AVAILABLE_META, isset( $_POST['laca_combo_available'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::BADGE_META, $badge );
		update_post_meta( $post_id, self::ITEMS_META, self::sanitize_items( $items ) );

		if ( class_exists( 'Laca_KDS_REST_API' ) ) {
			Laca_KDS_REST_API::flush_menu_cache();
		}
	}

	/**
	 * Customize combo list columns.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public static function filter_admin_columns( $columns ) {
		$new_columns = array();

		if ( isset( $columns['cb'] ) ) {
			$new_columns['cb'] = $columns['cb'];
		}

		$new_columns['title']               = isset( $columns['title'] ) ? $columns['title'] : __( 'Combo', 'laca-self-ordering-kds' );
		$new_columns['laca_combo_items']   = __( 'Món trong combo', 'laca-self-ordering-kds' );
		$new_columns['laca_combo_original'] = __( 'Giá gốc', 'laca-self-ordering-kds' );
		$new_columns['laca_combo_price']   = __( 'Giá combo', 'laca-self-ordering-kds' );
		$new_columns['laca_combo_status']  = __( 'Trạng thái', 'laca-self-ordering-kds' );

		if ( isset( $columns['taxonomy-' . Laca_KDS_Food_CPT::TAXONOMY] ) ) {
			$new_columns['taxonomy-' . Laca_KDS_Food_CPT::TAXONOMY] = $columns['taxonomy-' . Laca_KDS_Food_CPT::TAXONOMY];
		}

		if ( isset( $columns['date'] ) ) {
			$new_columns['date'] = $columns['date'];
		}

		return $new_columns;
	}

	/**
	 * Render combo list columns.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_admin_column( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'laca_combo_items':
				echo esc_html( self::get_details( $post_id ) ? self::get_details( $post_id ) : __( 'Chưa chọn món', 'laca-self-ordering-kds' ) );
				break;

			case 'laca_combo_original':
				echo esc_html( number_format_i18n( self::get_original_price( $post_id ) ) ) . 'đ';
				break;

			case 'laca_combo_price':
				echo '<strong>' . esc_html( number_format_i18n( self::get_price( $post_id ) ) ) . 'đ</strong>';
				break;

			case 'laca_combo_status':
				echo self::is_available( $post_id ) ? esc_html__( 'Đang bán', 'laca-self-ordering-kds' ) : esc_html__( 'Đang ẩn / thiếu món', 'laca-self-ordering-kds' );
				break;
		}
	}

	/**
	 * Sanitize combo item rows.
	 *
	 * @param array $items Raw items.
	 * @return array
	 */
	private static function sanitize_items( $items ) {
		$sanitized = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$food_id  = isset( $item['food_id'] ) ? absint( $item['food_id'] ) : 0;
			$quantity = isset( $item['quantity'] ) ? min( 99, max( 1, absint( $item['quantity'] ) ) ) : 1;

			if ( ! $food_id || Laca_KDS_Food_CPT::POST_TYPE !== get_post_type( $food_id ) ) {
				continue;
			}

			$key = (string) $food_id;
			if ( isset( $sanitized[ $key ] ) ) {
				continue;
			}

			$sanitized[ $key ] = array(
				'food_id'  => $food_id,
				'quantity' => $quantity,
			);
		}

		return array_values( $sanitized );
	}

	/**
	 * Get food options for combo builder.
	 *
	 * @return array
	 */
	private static function get_food_options() {
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
	 * Get combo price.
	 *
	 * @param int $post_id Combo ID.
	 * @return float
	 */
	public static function get_price( $post_id ) {
		return (float) get_post_meta( absint( $post_id ), self::PRICE_META, true );
	}

	/**
	 * Get combo badge.
	 *
	 * @param int $post_id Combo ID.
	 * @return string
	 */
	public static function get_badge( $post_id ) {
		$badge = sanitize_text_field( get_post_meta( absint( $post_id ), self::BADGE_META, true ) );

		return '' !== $badge ? $badge : __( 'Combo tiết kiệm', 'laca-self-ordering-kds' );
	}

	/**
	 * Check whether the combo and its component foods can be ordered.
	 *
	 * @param int $post_id Combo ID.
	 * @return bool
	 */
	public static function is_available( $post_id ) {
		$value = get_post_meta( absint( $post_id ), self::AVAILABLE_META, true );
		if ( '0' === $value || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}

		$items = self::get_items( $post_id );
		if ( empty( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( empty( $item['is_available'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get raw combo item rows.
	 *
	 * @param int $post_id Combo ID.
	 * @return array
	 */
	public static function get_raw_items( $post_id ) {
		$items = get_post_meta( absint( $post_id ), self::ITEMS_META, true );

		return is_array( $items ) ? self::sanitize_items( $items ) : array();
	}

	/**
	 * Get enriched combo items.
	 *
	 * @param int $post_id Combo ID.
	 * @return array
	 */
	public static function get_items( $post_id ) {
		$items = array();

		foreach ( self::get_raw_items( $post_id ) as $item ) {
			$food_id = absint( $item['food_id'] );
			if ( ! $food_id || Laca_KDS_Food_CPT::POST_TYPE !== get_post_type( $food_id ) || 'publish' !== get_post_status( $food_id ) ) {
				continue;
			}

			$quantity = max( 1, absint( $item['quantity'] ) );
			$price    = Laca_KDS_Food_CPT::get_price( $food_id );
			$items[]  = array(
				'food_id'      => $food_id,
				'name'         => get_the_title( $food_id ),
				'quantity'     => $quantity,
				'price'        => $price,
				'line_total'   => $price * $quantity,
				'is_available' => Laca_KDS_Food_CPT::is_available( $food_id ),
			);
		}

		return $items;
	}

	/**
	 * Get the sum of component food prices.
	 *
	 * @param int $post_id Combo ID.
	 * @return float
	 */
	public static function get_original_price( $post_id ) {
		return array_sum( wp_list_pluck( self::get_items( $post_id ), 'line_total' ) );
	}

	/**
	 * Get a compact component description.
	 *
	 * @param int $post_id Combo ID.
	 * @return string
	 */
	public static function get_details( $post_id ) {
		$parts = array();

		foreach ( self::get_items( $post_id ) as $item ) {
			$parts[] = sprintf( '%dx %s', absint( $item['quantity'] ), sanitize_text_field( $item['name'] ) );
		}

		return implode( ' + ', $parts );
	}

	/**
	 * Build a meta query that hides unavailable combos while keeping legacy combos visible.
	 *
	 * @return array
	 */
	public static function available_meta_query() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => self::AVAILABLE_META,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::AVAILABLE_META,
				'value'   => '1',
				'compare' => '=',
			),
		);
	}
}
