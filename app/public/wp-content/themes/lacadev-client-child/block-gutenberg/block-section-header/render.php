<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

$raw_attrs = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$wrapper   = lacadev_block_get_wrapper_attributes(
	'laca-section-header-block is-width-' . sanitize_html_class( $attributes['maxWidth'] ?? 'normal' ),
	$attributes,
	'--laca-section-header',
	$raw_attrs
);
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-section-header-block__inner'
);

if ( '' === $section_header ) {
	return;
}

$show_divider = lacadev_block_attr_to_bool( $attributes['showDivider'] ?? false, false );
$divider_color = sanitize_hex_color( $attributes['dividerColor'] ?? '#dbe3f0' ) ?: '#dbe3f0';
$background = lacadev_block_get_background_rgba( $attributes, '#ffffff' );
?>

<section <?php echo $wrapper; ?>>
	<div class="laca-section-header-block__surface" style="background:<?php echo esc_attr( $background ); ?>;">
		<?php echo $section_header; ?>
		<?php if ( $show_divider ) : ?>
			<div class="laca-section-header-block__divider" style="background-color:<?php echo esc_attr( $divider_color ); ?>;"></div>
		<?php endif; ?>
	</div>
</section>