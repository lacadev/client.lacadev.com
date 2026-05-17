<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

$raw_attrs = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$wrapper   = lacadev_block_get_wrapper_attributes(
	'laca-cta-block is-tone-' . sanitize_html_class( $attributes['tone'] ?? 'light' ),
	$attributes,
	'--laca-cta',
	$raw_attrs
);
$background = lacadev_block_get_background_rgba( $attributes, '#f8fafc' );
$description = sanitize_textarea_field( $attributes['description'] ?? '' );
$button_align = lacadev_block_normalize_text_align( sanitize_key( $attributes['buttonAlign'] ?? 'left' ) );
$primary = lacadev_block_render_button(
	(string) ( $attributes['primaryLabel'] ?? '' ),
	(string) ( $attributes['primaryUrl'] ?? '' ),
	'primary',
	lacadev_block_attr_to_bool( $attributes['primaryNewTab'] ?? false, false )
);
$secondary = lacadev_block_render_button(
	(string) ( $attributes['secondaryLabel'] ?? '' ),
	(string) ( $attributes['secondaryUrl'] ?? '' ),
	'secondary',
	lacadev_block_attr_to_bool( $attributes['secondaryNewTab'] ?? false, false )
);
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-cta-block__header'
);
?>

<section <?php echo $wrapper; ?>>
	<div class="laca-cta-block__inner" style="background:<?php echo esc_attr( $background ); ?>;">
		<?php echo $section_header; ?>
		<?php if ( '' !== $description ) : ?>
			<p class="laca-cta-block__description" style="text-align:<?php echo esc_attr( $button_align ); ?>;">
				<?php echo esc_html( $description ); ?>
			</p>
		<?php endif; ?>
		<?php if ( '' !== $primary || '' !== $secondary ) : ?>
			<div class="laca-cta-block__actions is-align-<?php echo esc_attr( $button_align ); ?>">
				<?php echo $primary; ?>
				<?php echo $secondary; ?>
			</div>
		<?php endif; ?>
	</div>
</section>