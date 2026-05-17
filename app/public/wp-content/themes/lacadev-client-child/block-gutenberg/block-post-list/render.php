<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

$raw_attrs = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$layout    = in_array( (string) ( $attributes['layout'] ?? 'grid' ), [ 'grid', 'list' ], true ) ? (string) $attributes['layout'] : 'grid';
$post_type = sanitize_key( $attributes['postType'] ?? 'post' );
$count     = max( 1, min( 8, intval( $attributes['postsPerPage'] ?? 3 ) ) );
$wrapper   = lacadev_block_get_wrapper_attributes(
	'laca-post-list-block is-layout-' . $layout,
	$attributes,
	'--laca-post-list',
	$raw_attrs
);
$background  = lacadev_block_get_background_rgba( $attributes, '#ffffff' );
$show_excerpt = lacadev_block_attr_to_bool( $attributes['showExcerpt'] ?? true, true );
$show_date    = lacadev_block_attr_to_bool( $attributes['showDate'] ?? true, true );
$button_label = sanitize_text_field( $attributes['buttonLabel'] ?? 'Đọc tiếp' );
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-post-list-block__header'
);
$query = new WP_Query(
	[
		'post_type' => post_type_exists( $post_type ) ? $post_type : 'post',
		'posts_per_page' => $count,
		'post_status' => 'publish',
		'ignore_sticky_posts' => true,
	]
);
?>

<section <?php echo $wrapper; ?>>
	<div class="laca-post-list-block__surface" style="background:<?php echo esc_attr( $background ); ?>;">
		<?php echo $section_header; ?>
		<div class="laca-post-list-block__grid is-layout-<?php echo esc_attr( $layout ); ?>">
			<?php if ( $query->have_posts() ) : ?>
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<article class="laca-post-list-block__card">
						<span class="laca-post-list-block__type"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? get_post_type() ); ?></span>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<?php if ( $show_date ) : ?>
							<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
						<?php endif; ?>
						<?php if ( $show_excerpt ) : ?>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<?php endif; ?>
						<a class="laca-post-list-block__link" href="<?php the_permalink(); ?>"><?php echo esc_html( $button_label ); ?></a>
					</article>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p><?php esc_html_e( 'Chưa có bài viết phù hợp.', 'lacadev' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>