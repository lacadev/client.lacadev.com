<?php
/**
 * Video Block - render.php
 *
 * @package LacaDev
 * @var array    $attributes Block attributes.
 * @var string   $content    Block inner content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/utils/render-helpers.php';

if ( ! function_exists( 'lacadev_block_attr_to_bool' ) ) {
	/**
	 * Normalize Gutenberg attribute values to boolean safely.
	 *
	 * @param mixed $value Raw attribute value.
	 * @param bool  $default Fallback value.
	 * @return bool
	 */
	function lacadev_block_attr_to_bool( $value, bool $default = false ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( null === $value ) {
			return $default;
		}

		$normalized = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		return null === $normalized ? $default : $normalized;
	}
}

// ── Appearance attributes ──────────────────────────────────────────────────
$bg_color     = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '' ) ? $attributes['bgColor'] : '#0f0f0f';
$bg_opacity   = max( 0, min( 100, intval( $attributes['bgOpacity'] ?? 100 ) ) );
$r = hexdec( substr( $bg_color, 1, 2 ) );
$g = hexdec( substr( $bg_color, 3, 2 ) );
$b = hexdec( substr( $bg_color, 5, 2 ) );
$bg_rgba = 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ( $bg_opacity / 100 ) . ')';

$source_type     = isset( $attributes['sourceType'] ) ? $attributes['sourceType'] : 'url';
$video_url       = isset( $attributes['videoUrl'] ) ? esc_url( $attributes['videoUrl'] ) : '';
$video_file      = isset( $attributes['videoFileUrl'] ) ? esc_url( $attributes['videoFileUrl'] ) : '';
$poster_url      = isset( $attributes['posterUrl'] ) ? esc_url( $attributes['posterUrl'] ) : '';
$autoplay        = lacadev_block_attr_to_bool( $attributes['autoplay'] ?? false, false );
$loop            = lacadev_block_attr_to_bool( $attributes['loop'] ?? false, false );
$muted           = lacadev_block_attr_to_bool( $attributes['muted'] ?? false, false );
$controls        = lacadev_block_attr_to_bool( $attributes['controls'] ?? true, true );

// Overlay
$overlay_enabled = ! empty( $attributes['overlayEnabled'] );
$overlay_color   = isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '#000000';
$overlay_opacity = isset( $attributes['overlayOpacity'] ) ? (int) $attributes['overlayOpacity'] : 40;
$overlay_text      = isset( $attributes['overlayText'] ) ? $attributes['overlayText'] : '';
$overlay_font_size = isset( $attributes['overlayFontSize'] ) ? (int) $attributes['overlayFontSize'] : 16;
$overlay_text_color = isset( $attributes['overlayTextColor'] ) ? $attributes['overlayTextColor'] : '#ffffff';
$overlay_text_align = isset( $attributes['overlayTextAlign'] ) ? $attributes['overlayTextAlign'] : 'center';
$overlay_vertical_align = isset( $attributes['overlayVerticalAlign'] ) ? $attributes['overlayVerticalAlign'] : 'center';
$text_align = $overlay_text_align === 'flex-start' ? 'left' : ( $overlay_text_align === 'flex-end' ? 'right' : 'center' );

// Tính opacity 0–1 từ 0–100
$opacity_css = round( $overlay_opacity / 100, 2 );

// Không render nếu không có video
$has_video = ( 'url' === $source_type && $video_url ) || ( 'file' === $source_type && $video_file );
if ( ! $has_video ) {
	return;
}

$raw_attrs  = is_array( $block->parsed_block['attrs'] ?? null ) ? $block->parsed_block['attrs'] : [];
$style_vars = lacadev_block_get_spacing_style_vars(
	$attributes,
	'--laca-video',
	$raw_attrs
);
$section_header = lacadev_block_render_section_header(
	$attributes,
	'laca-block-section-header laca-video-block__header',
	'laca-video-block__heading',
	'laca-video-block__subheading'
);

$wrapper_args = [ 'class' => 'laca-video-block' ];
if ( ! empty( $style_vars ) ) {
	$wrapper_args['style'] = implode( ';', $style_vars ) . ';';
}

$wrapper_attrs = get_block_wrapper_attributes( $wrapper_args );

/**
 * Helper: parse iframe từ các URL phổ biến
 */
if ( ! function_exists( 'lacadev_parse_video_url' ) ) {
	function lacadev_parse_video_url( string $url ): array {
		$result = [ 'type' => 'unknown', 'embed' => '' ];

		// YouTube
			if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
			$result['type']  = 'youtube';
			$result['embed'] = 'https://www.youtube.com/embed/' . $m[1];
			return $result;
		}

		// Vimeo
		if ( preg_match( '/vimeo\.com\/(\d+)/', $url, $m ) ) {
			$result['type']  = 'vimeo';
			$result['embed'] = 'https://player.vimeo.com/video/' . $m[1];
			return $result;
		}

		$result['type'] = 'direct';
		return $result;
	}
}
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="laca-video-block__inner" style="background:<?php echo esc_attr($bg_rgba); ?>;">
        <?php if ( '' !== $section_header ) : ?>
            <?php echo $section_header; ?>
        <?php endif; ?>

        <div class="laca-video-block__media-wrap">
        <?php if ( 'url' === $source_type && $video_url ) :
            $parsed = lacadev_parse_video_url( $video_url );

            if ( in_array( $parsed['type'], [ 'youtube', 'vimeo' ], true ) ) :
                $embed_url = $parsed['embed'];

                // Thêm params autoplay / loop
				$params = [];

					if ( 'youtube' === $parsed['type'] ) {
						$params['autoplay']        = $autoplay ? '1' : '0';
						$params['controls']        = $controls ? '1' : '0';
						$params['fs']              = $controls ? '1' : '0';
						$params['disablekb']       = $controls ? '0' : '1';
						$params['iv_load_policy']  = $controls ? '1' : '3';
						$params['loop']            = $loop ? '1' : '0';
						$params['mute']            = $muted ? '1' : '0';
						$params['modestbranding']  = '1';
						$params['playsinline']     = '1';
						$params['rel']             = '0';
					if ( $loop && ! empty( $parsed['embed'] ) ) {
						$params['playlist'] = preg_replace( '#^https://www\.youtube\.com/embed/#', '', $parsed['embed'] );
					}
					} else {
						$params['autoplay'] = $autoplay ? '1' : '0';
						$params['loop']     = $loop ? '1' : '0';
						$params['muted']    = $muted ? '1' : '0';
						$params['controls'] = $controls ? '1' : '0';
						$params['title']    = $controls ? '1' : '0';
						$params['byline']   = $controls ? '1' : '0';
						$params['portrait'] = $controls ? '1' : '0';
					}

				$embed_url = add_query_arg( $params, $embed_url );
	            ?>
                <div class="laca-video-block__iframe-wrap">
                    <iframe
                        src="<?php echo esc_url( $embed_url ); ?>"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        loading="lazy"
                        title="<?php esc_attr_e( 'Video nhúng', 'lacadev' ); ?>"
                    ></iframe>
                </div>
            <?php else : // direct URL — dùng thẻ <video> ?>
                <div class="laca-video-block__video-wrap">
                    <video
                        src="<?php echo esc_url( $video_url ); ?>"
                        <?php if ( $poster_url ) : ?>poster="<?php echo esc_url( $poster_url ); ?>"<?php endif; ?>
                        <?php if ( $controls ) echo 'controls'; ?>
                        <?php if ( $autoplay ) echo 'autoplay'; ?>
                        <?php if ( $loop )     echo 'loop'; ?>
                        <?php if ( $muted )    echo 'muted'; ?>
                        playsinline
                        preload="metadata"
                    ></video>
                </div>
            <?php endif; ?>

        <?php elseif ( 'file' === $source_type && $video_file ) : ?>
            <div class="laca-video-block__video-wrap">
                <video
                    src="<?php echo esc_url( $video_file ); ?>"
                    <?php if ( $poster_url ) : ?>poster="<?php echo esc_url( $poster_url ); ?>"<?php endif; ?>
                    <?php if ( $controls ) echo 'controls'; ?>
                    <?php if ( $autoplay ) echo 'autoplay'; ?>
                    <?php if ( $loop )     echo 'loop'; ?>
                    <?php if ( $muted )    echo 'muted'; ?>
                    playsinline
                    preload="metadata"
                ></video>
            </div>
        <?php endif; ?>

        <?php if ( $overlay_enabled ) : ?>
            <div
                class="laca-video-block__overlay"
                style="background-color:<?php echo esc_attr( $overlay_color ); ?>;opacity:<?php echo esc_attr( $opacity_css ); ?>;"
                aria-hidden="true"
            ></div>
            <?php if ( $content || $overlay_text ) : ?>
            <div class="laca-video-block__overlay-text" style="align-items:<?php echo esc_attr( $overlay_vertical_align ); ?>;justify-content:<?php echo esc_attr( $overlay_text_align ); ?>;color:<?php echo esc_attr( $overlay_text_color ); ?>;font-size:<?php echo esc_attr( $overlay_font_size ); ?>px;text-align:<?php echo esc_attr( $text_align ); ?>;width:100%;flex-direction:column;">
                <?php echo $content ? $content : wp_kses_post( $overlay_text ); ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        </div><!-- .laca-video-block__media-wrap -->
    </div><!-- .laca-video-block__inner -->
</section>
