<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

$raw_attrs = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$columns   = in_array( (string) ( $attributes['columns'] ?? '3' ), [ '2', '3', '4' ], true ) ? (string) $attributes['columns'] : '3';
$card_tone = sanitize_html_class( $attributes['cardTone'] ?? 'soft' );
$items     = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
$wrapper   = lacadev_block_get_wrapper_attributes(
	'laca-feature-grid-block is-tone-' . $card_tone . ' columns-' . $columns,
	$attributes,
	'--laca-feature-grid',
	$raw_attrs
);
$background = lacadev_block_get_background_rgba( $attributes, '#ffffff' );
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-feature-grid-block__header'
);
?>

<section <?php echo $wrapper; ?>>
	<div class="laca-feature-grid-block__surface" style="background:<?php echo esc_attr( $background ); ?>;">
		<?php echo $section_header; ?>
		<div class="laca-feature-grid-block__grid columns-<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $items as $item ) : ?>
				<article class="laca-feature-grid-block__card">
					<?php if ( ! empty( $item['kicker'] ) ) : ?>
						<span class="laca-feature-grid-block__kicker"><?php echo esc_html( $item['kicker'] ); ?></span>
					<?php endif; ?>
					<h3 class="laca-feature-grid-block__title"><?php echo esc_html( $item['title'] ?? '' ); ?></h3>
					<p class="laca-feature-grid-block__text"><?php echo esc_html( $item['text'] ?? '' ); ?></p>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>