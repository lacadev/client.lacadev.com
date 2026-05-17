<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

$raw_attrs = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$wrapper   = lacadev_block_get_wrapper_attributes(
	'laca-hero-block is-layout-' . sanitize_html_class( $attributes['layout'] ?? 'split' ) . ' is-media-' . sanitize_html_class( $attributes['mediaPosition'] ?? 'right' ),
	$attributes,
	'--laca-hero',
	$raw_attrs
);
$background = lacadev_block_get_background_rgba( $attributes, '#0f172a' );
$badge = sanitize_text_field( $attributes['badge'] ?? '' );
$description = sanitize_textarea_field( $attributes['description'] ?? '' );
$media_url = esc_url( $attributes['mediaUrl'] ?? '' );
$media_alt = sanitize_text_field( $attributes['mediaAlt'] ?? '' );
$primary = lacadev_block_render_button( (string) ( $attributes['primaryLabel'] ?? '' ), (string) ( $attributes['primaryUrl'] ?? '' ), 'light' );
$secondary = lacadev_block_render_button( (string) ( $attributes['secondaryLabel'] ?? '' ), (string) ( $attributes['secondaryUrl'] ?? '' ), 'secondary' );
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-hero-block__header'
);
?>

<section <?php echo $wrapper; ?>>
	<div class="laca-hero-block__inner" style="background:<?php echo esc_attr( $background ); ?>;">
		<div class="laca-hero-block__content">
			<?php if ( '' !== $badge ) : ?>
				<span class="laca-hero-block__badge"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>
			<?php echo $section_header; ?>
			<?php if ( '' !== $description ) : ?>
				<p class="laca-hero-block__description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $primary || '' !== $secondary ) : ?>
				<div class="laca-hero-block__actions">
					<?php echo $primary; ?>
					<?php echo $secondary; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="laca-hero-block__media">
			<?php if ( '' !== $media_url ) : ?>
				<img src="<?php echo esc_url( $media_url ); ?>" alt="<?php echo esc_attr( $media_alt ); ?>" />
			<?php endif; ?>
		</div>
	</div>
</section>