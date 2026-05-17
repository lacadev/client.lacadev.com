<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

$raw_attrs = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$columns   = in_array( (string) ( $attributes['columns'] ?? '3' ), [ '1', '2', '3' ], true ) ? (string) $attributes['columns'] : '3';
$tone      = sanitize_html_class( $attributes['tone'] ?? 'soft' );
$items     = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
$wrapper   = lacadev_block_get_wrapper_attributes(
	'laca-testimonial-block is-tone-' . $tone . ' columns-' . $columns,
	$attributes,
	'--laca-testimonial',
	$raw_attrs
);
$background = lacadev_block_get_background_rgba( $attributes, '#ffffff' );
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-testimonial-block__header'
);
?>

<section <?php echo $wrapper; ?>>
	<div class="laca-testimonial-block__surface" style="background:<?php echo esc_attr( $background ); ?>;">
		<?php echo $section_header; ?>
		<div class="laca-testimonial-block__grid columns-<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<article class="laca-testimonial-block__card">
					<p class="laca-testimonial-block__quote">"<?php echo esc_html( $item['quote'] ?? '' ); ?>"</p>
					<strong class="laca-testimonial-block__name"><?php echo esc_html( $item['name'] ?? '' ); ?></strong>
					<span class="laca-testimonial-block__role"><?php echo esc_html( $item['role'] ?? '' ); ?></span>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>