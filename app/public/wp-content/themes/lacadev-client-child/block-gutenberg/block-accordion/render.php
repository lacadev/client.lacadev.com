<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

$raw_attrs = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$wrapper   = lacadev_block_get_wrapper_attributes(
	'laca-accordion-block is-tone-' . sanitize_html_class( $attributes['tone'] ?? 'soft' ),
	$attributes,
	'--laca-accordion',
	$raw_attrs
);
$background = lacadev_block_get_background_rgba( $attributes, '#ffffff' );
$items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : [];
$open_first = lacadev_block_attr_to_bool( $attributes['openFirst'] ?? true, true );
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-accordion-block__header'
);
?>

<section <?php echo $wrapper; ?>>
	<div class="laca-accordion-block__surface" style="background:<?php echo esc_attr( $background ); ?>;">
		<?php echo $section_header; ?>
		<div class="laca-accordion-block__items">
			<?php foreach ( $items as $index => $item ) : ?>
				<details class="laca-accordion-block__item" <?php echo ( $open_first && 0 === $index ) ? 'open' : ''; ?>>
					<summary><?php echo esc_html( $item['question'] ?? '' ); ?></summary>
					<p><?php echo esc_html( $item['answer'] ?? '' ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>