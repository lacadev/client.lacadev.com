<?php
/**
 * Food menu item custom post type.
 *
 * @package LacaSelfOrderingKDS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers laca_food and its price meta box.
 */
class Laca_KDS_Food_CPT {
	const POST_TYPE  = 'laca_food';
	const TAXONOMY   = 'laca_food_category';
	const PRICE_META = '_laca_food_price';
	const AVAILABLE_META = '_laca_food_available';
	const VARIANTS_META = '_laca_food_variants';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'maybe_seed_default_terms' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_price_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_price_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_list_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'filter_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'filter_sortable_columns' ) );
		add_filter( 'list_table_primary_column', array( __CLASS__, 'filter_primary_column' ), 10, 2 );
		add_action( 'wp_ajax_laca_kds_inline_update_food', array( __CLASS__, 'ajax_inline_update_food' ) );
	}

	/**
	 * Register the food CPT.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Món ăn', 'laca-self-ordering-kds' ),
			'singular_name'      => __( 'Món ăn', 'laca-self-ordering-kds' ),
			'menu_name'          => __( 'Món ăn', 'laca-self-ordering-kds' ),
			'all_items'          => __( 'Danh sách món', 'laca-self-ordering-kds' ),
			'add_new'            => __( 'Thêm món', 'laca-self-ordering-kds' ),
			'add_new_item'       => __( 'Thêm món mới', 'laca-self-ordering-kds' ),
			'edit_item'          => __( 'Sửa món', 'laca-self-ordering-kds' ),
			'new_item'           => __( 'Món mới', 'laca-self-ordering-kds' ),
			'view_item'          => __( 'Xem món', 'laca-self-ordering-kds' ),
			'search_items'       => __( 'Tìm món', 'laca-self-ordering-kds' ),
			'not_found'          => __( 'Chưa có món.', 'laca-self-ordering-kds' ),
			'not_found_in_trash' => __( 'Không có món trong thùng rác.', 'laca-self-ordering-kds' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => class_exists( 'Laca_KDS_Admin_KDS' ) ? Laca_KDS_Admin_KDS::MENU_SLUG : true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-food',
				'supports'           => array( 'title', 'thumbnail' ),
				'taxonomies'         => array( self::TAXONOMY ),
				'has_archive'        => false,
				'rewrite'            => array( 'slug' => 'laca-food' ),
				'capability_type'    => 'post',
				'publicly_queryable' => false,
			)
		);
	}

	/**
	 * Register food categories for quick customer filtering.
	 *
	 * @return void
	 */
	public static function register_taxonomy() {
		$labels = array(
			'name'          => __( 'Danh mục món', 'laca-self-ordering-kds' ),
			'singular_name' => __( 'Danh mục món', 'laca-self-ordering-kds' ),
			'menu_name'     => __( 'Danh mục món', 'laca-self-ordering-kds' ),
			'all_items'     => __( 'Tất cả danh mục món', 'laca-self-ordering-kds' ),
			'edit_item'     => __( 'Sửa danh mục món', 'laca-self-ordering-kds' ),
			'add_new_item'  => __( 'Thêm danh mục món', 'laca-self-ordering-kds' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			class_exists( 'Laca_KDS_Combo_CPT' ) ? array( self::POST_TYPE, Laca_KDS_Combo_CPT::POST_TYPE ) : self::POST_TYPE,
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'laca-food-category' ),
			)
		);
	}

	/**
	 * Create practical starter categories once.
	 *
	 * @return void
	 */
	public static function maybe_seed_default_terms() {
		if ( get_option( 'laca_kds_seeded_food_categories' ) ) {
			return;
		}

		$default_terms = array(
			'Đồ ăn vặt' => 'do-an-vat',
			'Nước'      => 'nuoc',
			'Món chính' => 'mon-chinh',
		);

		foreach ( $default_terms as $name => $slug ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term(
					$name,
					self::TAXONOMY,
					array(
						'slug' => $slug,
					)
				);
			}
		}

		update_option( 'laca_kds_seeded_food_categories', '1', false );
	}

	/**
	 * Add the price meta box.
	 *
	 * @return void
	 */
	public static function add_price_meta_box() {
		add_meta_box(
			'laca_food_price',
			__( 'Food Price', 'laca-self-ordering-kds' ),
			array( __CLASS__, 'render_price_meta_box' ),
			self::POST_TYPE,
			'side',
			'high'
		);

		add_meta_box(
			'laca_food_variants',
			__( 'Tùy chọn món / Variant', 'laca-self-ordering-kds' ),
			array( __CLASS__, 'render_variants_meta_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the price meta box.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function render_price_meta_box( $post ) {
		$price        = self::get_price( $post->ID );
		$is_available = self::is_available( $post->ID );

		wp_nonce_field( 'laca_food_price_nonce_action', 'laca_food_price_nonce' );
		?>
		<p>
			<label for="laca_food_price"><?php esc_html_e( 'Price (VND)', 'laca-self-ordering-kds' ); ?></label>
			<input
				type="number"
				id="laca_food_price"
				name="laca_food_price"
				value="<?php echo esc_attr( $price ); ?>"
				min="0"
				step="1000"
				style="width: 100%;"
			/>
		</p>
		<p>
			<label>
				<input type="checkbox" name="laca_food_available" value="1" <?php checked( $is_available ); ?> />
				<?php esc_html_e( 'Còn món / Available', 'laca-self-ordering-kds' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Render variant groups for size, spice, sauce, and other options.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function render_variants_meta_box( $post ) {
		$variants = self::get_variants( $post->ID );
		$groups   = ! empty( $variants ) ? $variants : array(
			array(
				'name'    => '',
				'options' => array(
					array(
						'name'        => '',
						'price_delta' => 0,
					),
				),
			),
		);
		?>
		<div class="laca-variant-builder">
			<div class="laca-variant-builder__hero">
				<div>
					<span><?php esc_html_e( 'Variant builder', 'laca-self-ordering-kds' ); ?></span>
					<h3><?php esc_html_e( 'Tùy chọn món theo size, vị, sốt', 'laca-self-ordering-kds' ); ?></h3>
					<p><?php esc_html_e( 'Khách sẽ chọn từng nhóm tùy chọn ngay trên menu. Giá món tự cộng phần chênh lệch và được lưu vào đơn/KDS.', 'laca-self-ordering-kds' ); ?></p>
				</div>
				<ul>
					<li><?php esc_html_e( 'Size: Nhỏ +0đ, Lớn +5.000đ', 'laca-self-ordering-kds' ); ?></li>
					<li><?php esc_html_e( 'Gia vị: Không cay, Ít cay, Cay nhiều', 'laca-self-ordering-kds' ); ?></li>
					<li><?php esc_html_e( 'Nước sốt: Sốt me, Sốt cay, Sốt tỏi', 'laca-self-ordering-kds' ); ?></li>
				</ul>
			</div>
			<div class="laca-variant-groups" data-next-index="<?php echo esc_attr( count( $groups ) ); ?>">
				<?php foreach ( $groups as $group_index => $group ) : ?>
					<?php self::render_variant_group( $group, $group_index ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-primary laca-variant-add-group"><?php esc_html_e( 'Thêm nhóm tùy chọn', 'laca-self-ordering-kds' ); ?></button>
			<template id="laca-variant-group-template">
				<?php self::render_variant_group( array(), '__group__' ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * Render a variant group row.
	 *
	 * @param array      $group Variant group.
	 * @param int|string $group_index Group index.
	 * @return void
	 */
	private static function render_variant_group( $group, $group_index ) {
		$group    = wp_parse_args(
			is_array( $group ) ? $group : array(),
			array(
				'name'    => '',
				'options' => array(),
			)
		);
		$options  = ! empty( $group['options'] ) && is_array( $group['options'] ) ? $group['options'] : array(
			array(
				'name'        => '',
				'price_delta' => 0,
			),
		);
		$base     = 'laca_food_variants[' . $group_index . ']';
		?>
		<div class="laca-variant-group" data-group-index="<?php echo esc_attr( $group_index ); ?>" data-next-option-index="<?php echo esc_attr( count( $options ) ); ?>">
			<div class="laca-variant-group__head">
				<label>
					<?php esc_html_e( 'Tên nhóm', 'laca-self-ordering-kds' ); ?>
					<input type="text" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( $group['name'] ); ?>" placeholder="<?php esc_attr_e( 'VD: Size / Gia vị / Nước sốt', 'laca-self-ordering-kds' ); ?>" />
				</label>
				<button type="button" class="button laca-variant-remove-group"><?php esc_html_e( 'Xóa nhóm', 'laca-self-ordering-kds' ); ?></button>
			</div>
			<div class="laca-variant-options">
				<?php foreach ( $options as $option_index => $option ) : ?>
					<?php self::render_variant_option( $option, $group_index, $option_index ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button laca-variant-add-option"><?php esc_html_e( 'Thêm lựa chọn', 'laca-self-ordering-kds' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Render a variant option row.
	 *
	 * @param array      $option Variant option.
	 * @param int|string $group_index Group index.
	 * @param int|string $option_index Option index.
	 * @return void
	 */
	private static function render_variant_option( $option, $group_index, $option_index ) {
		$option = wp_parse_args(
			is_array( $option ) ? $option : array(),
			array(
				'name'        => '',
				'price_delta' => 0,
			)
		);
		$base   = 'laca_food_variants[' . $group_index . '][options][' . $option_index . ']';
		?>
		<div class="laca-variant-option">
			<label>
				<?php esc_html_e( 'Tên lựa chọn', 'laca-self-ordering-kds' ); ?>
				<input type="text" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( $option['name'] ); ?>" placeholder="<?php esc_attr_e( 'VD: Lớn / Ít cay / Sốt me', 'laca-self-ordering-kds' ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'Cộng thêm', 'laca-self-ordering-kds' ); ?>
				<input type="number" step="any" name="<?php echo esc_attr( $base ); ?>[price_delta]" value="<?php echo esc_attr( $option['price_delta'] ); ?>" />
			</label>
			<button type="button" class="button laca-variant-remove-option"><?php esc_html_e( 'Xóa', 'laca-self-ordering-kds' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Save the food price.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public static function save_price_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['laca_food_price_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['laca_food_price_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'laca_food_price_nonce_action' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( self::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$price = isset( $_POST['laca_food_price'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['laca_food_price'] ) ) : 0;
		$price = max( 0, $price );

		update_post_meta( $post_id, self::PRICE_META, $price );
		update_post_meta( $post_id, self::AVAILABLE_META, isset( $_POST['laca_food_available'] ) ? '1' : '0' );
		update_post_meta( $post_id, self::VARIANTS_META, self::sanitize_variants( isset( $_POST['laca_food_variants'] ) ? wp_unslash( $_POST['laca_food_variants'] ) : array() ) );

		if ( class_exists( 'Laca_KDS_REST_API' ) ) {
			Laca_KDS_REST_API::flush_menu_cache();
		}
	}

	/**
	 * Enqueue inline editing assets on the laca_food list screen.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_list_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'edit.php', 'post.php', 'post-new.php', 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_food_screen     = self::POST_TYPE === $screen->post_type;
		$is_category_screen = self::TAXONOMY === $screen->taxonomy;

		if ( ! $is_food_screen && ! $is_category_screen ) {
			return;
		}

		wp_enqueue_style(
			'laca-kds-admin',
			LACA_KDS_PLUGIN_URL . 'assets/css/admin-kds.css',
			array(),
			LACA_KDS_VERSION
		);
		laca_kds_enqueue_quicksand_font( 'laca-kds-admin' );

		if ( $is_category_screen ) {
			return;
		}

		wp_enqueue_media();

		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_script(
				'laca-kds-food-variants',
				LACA_KDS_PLUGIN_URL . 'assets/js/food-variants.js',
				array( 'jquery' ),
				LACA_KDS_VERSION,
				true
			);
			return;
		}

		wp_enqueue_script(
			'laca-kds-food-list-inline',
			LACA_KDS_PLUGIN_URL . 'assets/js/food-list-inline.js',
			array( 'jquery' ),
			LACA_KDS_VERSION,
			true
		);

		wp_localize_script(
			'laca-kds-food-list-inline',
			'lacaKdsFoodList',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'laca_kds_food_inline_nonce' ),
				'i18n'    => array(
					'chooseImage' => __( 'Chọn ảnh món', 'laca-self-ordering-kds' ),
					'useImage'    => __( 'Dùng ảnh này', 'laca-self-ordering-kds' ),
					'saving'      => __( 'Đang lưu...', 'laca-self-ordering-kds' ),
					'saved'       => __( 'Đã lưu', 'laca-self-ordering-kds' ),
					'error'       => __( 'Không thể lưu. Vui lòng thử lại.', 'laca-self-ordering-kds' ),
					'titleError'  => __( 'Tên món không được để trống.', 'laca-self-ordering-kds' ),
					'dirty'       => __( 'Có thay đổi chưa lưu', 'laca-self-ordering-kds' ),
					'removeImage' => __( 'Gỡ ảnh', 'laca-self-ordering-kds' ),
					'noImage'     => __( 'Chưa có ảnh', 'laca-self-ordering-kds' ),
					'autosave'    => __( 'Tự lưu', 'laca-self-ordering-kds' ),
				),
			)
		);
	}

	/**
	 * Customize the food admin list columns for fast editing.
	 *
	 * @param array $columns Default columns.
	 * @return array
	 */
	public static function filter_admin_columns( $columns ) {
		$new_columns = array();

		if ( isset( $columns['cb'] ) ) {
			$new_columns['cb'] = $columns['cb'];
		}

		$new_columns['laca_food_thumb']        = __( 'Ảnh', 'laca-self-ordering-kds' );
		$new_columns['laca_food_inline_title'] = __( 'Tên món', 'laca-self-ordering-kds' );

		foreach ( $columns as $key => $label ) {
			if ( in_array( $key, array( 'cb', 'title', 'date' ), true ) ) {
				continue;
			}

			$new_columns[ $key ] = $label;
		}

		$new_columns['laca_food_price']     = __( 'Giá', 'laca-self-ordering-kds' );
		$new_columns['laca_food_available'] = __( 'Còn món', 'laca-self-ordering-kds' );
		$new_columns['laca_food_save']      = __( 'Tự lưu', 'laca-self-ordering-kds' );

		if ( isset( $columns['date'] ) ) {
			$new_columns['date'] = $columns['date'];
		}

		return $new_columns;
	}

	/**
	 * Render fast-editable columns on the food admin list.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_admin_column( $column_name, $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		switch ( $column_name ) {
			case 'laca_food_thumb':
				self::render_thumbnail_column( $post_id );
				break;

			case 'laca_food_inline_title':
				self::render_title_column( $post_id );
				break;

			case 'laca_food_price':
				self::render_price_column( $post_id );
				break;

			case 'laca_food_available':
				self::render_available_column( $post_id );
				break;

			case 'laca_food_save':
				self::render_save_column();
				break;
		}
	}

	/**
	 * Make list sorting still work after replacing the default title column.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function filter_sortable_columns( $columns ) {
		$columns['laca_food_inline_title'] = 'title';

		return $columns;
	}

	/**
	 * Keep row actions under the inline title column.
	 *
	 * @param string $default_column Default primary column.
	 * @param string $screen_id Current screen ID.
	 * @return string
	 */
	public static function filter_primary_column( $default_column, $screen_id ) {
		if ( 'edit-' . self::POST_TYPE === $screen_id ) {
			return 'laca_food_inline_title';
		}

		return $default_column;
	}

	/**
	 * Render the thumbnail fast-edit column.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function render_thumbnail_column( $post_id ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		$image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' ) : '';
		?>
		<div class="laca-food-inline-thumb" data-thumbnail-id="<?php echo esc_attr( $thumbnail_id ); ?>">
			<button type="button" class="laca-food-inline-image-button" aria-label="<?php esc_attr_e( 'Đổi ảnh món', 'laca-self-ordering-kds' ); ?>">
				<span class="laca-food-inline-thumb__preview">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
					<?php else : ?>
						<span><?php esc_html_e( 'Chạm để chọn ảnh', 'laca-self-ordering-kds' ); ?></span>
					<?php endif; ?>
				</span>
				<span class="laca-food-inline-thumb__hint"><?php esc_html_e( 'Bấm ảnh để đổi', 'laca-self-ordering-kds' ); ?></span>
			</button>
			<button type="button" class="laca-food-inline-remove-image<?php echo $thumbnail_id ? '' : ' is-hidden'; ?>" aria-label="<?php esc_attr_e( 'Gỡ ảnh', 'laca-self-ordering-kds' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the title fast-edit column.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function render_title_column( $post_id ) {
		$title = get_the_title( $post_id );
		?>
		<div class="laca-food-inline-field">
			<label class="screen-reader-text" for="laca-food-title-<?php echo esc_attr( $post_id ); ?>">
				<?php esc_html_e( 'Tên món', 'laca-self-ordering-kds' ); ?>
			</label>
			<input
				type="text"
				id="laca-food-title-<?php echo esc_attr( $post_id ); ?>"
				class="laca-food-inline-title"
				value="<?php echo esc_attr( $title ); ?>"
			/>
			<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="laca-food-inline-detail-link">
				<?php esc_html_e( 'Mở chi tiết', 'laca-self-ordering-kds' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Render the price fast-edit column.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function render_price_column( $post_id ) {
		$price = self::get_price( $post_id );
		?>
		<label class="screen-reader-text" for="laca-food-price-<?php echo esc_attr( $post_id ); ?>">
			<?php esc_html_e( 'Giá', 'laca-self-ordering-kds' ); ?>
		</label>
		<input
			type="number"
			id="laca-food-price-<?php echo esc_attr( $post_id ); ?>"
			class="laca-food-inline-price"
			value="<?php echo esc_attr( $price ); ?>"
			min="0"
			step="1000"
			inputmode="numeric"
		/>
		<span class="laca-food-inline-currency" aria-hidden="true">đ</span>
		<?php
	}

	/**
	 * Render the availability fast-edit column.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function render_available_column( $post_id ) {
		$is_available = self::is_available( $post_id );
		?>
		<label class="laca-food-inline-switch">
			<input type="checkbox" class="laca-food-inline-available" value="1" <?php checked( $is_available ); ?> />
			<span><?php esc_html_e( 'Còn bán', 'laca-self-ordering-kds' ); ?></span>
		</label>
		<?php
	}

	/**
	 * Render the fast-save column.
	 *
	 * @return void
	 */
	private static function render_save_column() {
		?>
		<div class="laca-food-inline-save-wrap">
			<span class="laca-food-inline-status is-idle" aria-live="polite">
				<?php esc_html_e( 'Tự lưu', 'laca-self-ordering-kds' ); ?>
			</span>
			<span class="laca-food-inline-status-help">
				<?php esc_html_e( 'Rời ô để lưu', 'laca-self-ordering-kds' ); ?>
			</span>
		</div>
		<?php
	}

	/**
	 * AJAX handler for fast editing food title, price, image, and availability.
	 *
	 * @return void
	 */
	public static function ajax_inline_update_food() {
		check_ajax_referer( 'laca_kds_food_inline_nonce', 'nonce' );

		$food_id = isset( $_POST['food_id'] ) ? absint( wp_unslash( $_POST['food_id'] ) ) : 0;
		$post    = $food_id ? get_post( $food_id ) : null;

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_send_json_error(
				array( 'message' => __( 'Món không tồn tại.', 'laca-self-ordering-kds' ) ),
				404
			);
		}

		if ( ! current_user_can( 'edit_post', $food_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Bạn không có quyền sửa món này.', 'laca-self-ordering-kds' ) ),
				403
			);
		}

		$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$price_raw     = isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '0';
		$price         = max( 0, (float) $price_raw );
		$thumbnail_id  = isset( $_POST['thumbnail_id'] ) ? absint( wp_unslash( $_POST['thumbnail_id'] ) ) : 0;
		$is_available  = isset( $_POST['is_available'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['is_available'] ) );

		if ( '' === trim( $title ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Tên món không được để trống.', 'laca-self-ordering-kds' ) ),
				400
			);
		}

		if ( $thumbnail_id && ! wp_attachment_is_image( $thumbnail_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Ảnh đã chọn không hợp lệ.', 'laca-self-ordering-kds' ) ),
				400
			);
		}

		$updated = wp_update_post(
			array(
				'ID'         => $food_id,
				'post_title' => $title,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error(
				array( 'message' => $updated->get_error_message() ),
				500
			);
		}

		update_post_meta( $food_id, self::PRICE_META, $price );
		update_post_meta( $food_id, self::AVAILABLE_META, $is_available ? '1' : '0' );

		if ( $thumbnail_id ) {
			set_post_thumbnail( $food_id, $thumbnail_id );
		} else {
			delete_post_thumbnail( $food_id );
		}

		if ( class_exists( 'Laca_KDS_REST_API' ) ) {
			Laca_KDS_REST_API::flush_menu_cache();
		}

		wp_send_json_success(
			array(
				'id'            => $food_id,
				'title'         => get_the_title( $food_id ),
				'price'         => self::get_price( $food_id ),
				'is_available'  => self::is_available( $food_id ),
				'thumbnail_id'  => get_post_thumbnail_id( $food_id ),
				'thumbnail_url' => get_the_post_thumbnail_url( $food_id, 'thumbnail' ),
			)
		);
	}

	/**
	 * Get a food item's price.
	 *
	 * @param int $post_id Post ID.
	 * @return float
	 */
	public static function get_price( $post_id ) {
		return (float) get_post_meta( absint( $post_id ), self::PRICE_META, true );
	}

	/**
	 * Sanitize variant groups.
	 *
	 * @param array $groups Raw variant groups.
	 * @return array
	 */
	public static function sanitize_variants( $groups ) {
		if ( ! is_array( $groups ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$group_name = isset( $group['name'] ) ? sanitize_text_field( $group['name'] ) : '';
			$options    = isset( $group['options'] ) && is_array( $group['options'] ) ? $group['options'] : array();
			$clean_options = array();

			foreach ( $options as $option ) {
				if ( ! is_array( $option ) ) {
					continue;
				}

				$option_name = isset( $option['name'] ) ? sanitize_text_field( $option['name'] ) : '';
				if ( '' === $option_name ) {
					continue;
				}

				$clean_options[] = array(
					'name'        => $option_name,
					'price_delta' => isset( $option['price_delta'] ) ? (float) sanitize_text_field( $option['price_delta'] ) : 0,
				);
			}

			if ( '' === $group_name || empty( $clean_options ) ) {
				continue;
			}

			$sanitized[] = array(
				'name'    => $group_name,
				'options' => array_values( $clean_options ),
			);
		}

		return array_values( $sanitized );
	}

	/**
	 * Get configured variant groups.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_variants( $post_id ) {
		$variants = get_post_meta( absint( $post_id ), self::VARIANTS_META, true );

		return is_array( $variants ) ? self::sanitize_variants( $variants ) : array();
	}

	/**
	 * Validate selected variant indexes and return labels/price delta.
	 *
	 * @param int   $post_id Food ID.
	 * @param array $selected Raw selected variant indexes keyed by group index.
	 * @return array
	 */
	public static function prepare_selected_variants( $post_id, $selected ) {
		$groups   = self::get_variants( $post_id );
		$selected = is_array( $selected ) ? $selected : array();
		$labels   = array();
		$total_delta = 0;

		foreach ( $groups as $group_index => $group ) {
			$option_index = isset( $selected[ $group_index ] ) ? absint( $selected[ $group_index ] ) : 0;
			if ( empty( $group['options'][ $option_index ] ) ) {
				$option_index = 0;
			}

			$option = $group['options'][ $option_index ];
			$delta  = isset( $option['price_delta'] ) ? (float) $option['price_delta'] : 0;
			$labels[] = array(
				'group'       => sanitize_text_field( $group['name'] ),
				'option'      => sanitize_text_field( $option['name'] ),
				'price_delta' => $delta,
			);
			$total_delta += $delta;
		}

		return array(
			'selected'    => $labels,
			'price_delta' => $total_delta,
		);
	}

	/**
	 * Check whether a food item is available for ordering.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_available( $post_id ) {
		$value = get_post_meta( absint( $post_id ), self::AVAILABLE_META, true );

		return '0' !== $value;
	}

	/**
	 * Build a meta query that hides sold-out food while keeping legacy items visible.
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
