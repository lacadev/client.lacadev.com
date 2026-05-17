<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lacadev_block_normalize_spacing_value' ) ) {
	function lacadev_block_normalize_spacing_value( $value ): string {
		if ( is_numeric( $value ) ) {
			return $value . 'px';
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^-?\d+(\.\d+)?(px|rem|em|vw|vh|%)$/', $value ) ) {
			return $value;
		}

		return '';
	}
}

if ( ! function_exists( 'lacadev_block_attr_to_bool' ) ) {
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

if ( ! function_exists( 'lacadev_block_normalize_text_align' ) ) {
	function lacadev_block_normalize_text_align( string $value ): string {
		return in_array( $value, [ 'left', 'center', 'right' ], true )
			? $value
			: 'left';
	}
}

if ( ! function_exists( 'lacadev_block_normalize_heading_tag' ) ) {
	function lacadev_block_normalize_heading_tag( string $value ): string {
		return in_array( $value, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true )
			? $value
			: 'h2';
	}
}

if ( ! function_exists( 'lacadev_block_get_background_rgba' ) ) {
	function lacadev_block_get_background_rgba(
		array $attributes,
		string $default = '#0f0f0f'
	): string {
		$bg_color = preg_match( '/^#[0-9a-fA-F]{6}$/', $attributes['bgColor'] ?? '' )
			? $attributes['bgColor']
			: $default;
		$bg_opacity = max( 0, min( 100, intval( $attributes['bgOpacity'] ?? 100 ) ) );
		$r = hexdec( substr( $bg_color, 1, 2 ) );
		$g = hexdec( substr( $bg_color, 3, 2 ) );
		$b = hexdec( substr( $bg_color, 5, 2 ) );

		return 'rgba(' . $r . ',' . $g . ',' . $b . ',' . ( $bg_opacity / 100 ) . ')';
	}
}

if ( ! function_exists( 'lacadev_block_get_spacing_style_vars' ) ) {
	function lacadev_block_get_spacing_style_vars(
		array $attributes,
		string $prefix,
		array $raw_attrs = []
	): array {
		$style_vars = [];
		$spacing    = is_array( $attributes['spacing'] ?? null ) ? $attributes['spacing'] : [];
		$devices    = [ 'desktop', 'tablet', 'mobile' ];
		$types      = [ 'margin', 'padding' ];
		$sides      = [ 'top', 'left', 'bottom', 'right' ];

		foreach ( $devices as $device ) {
			foreach ( $types as $type ) {
				foreach ( $sides as $side ) {
					$raw_value = $spacing[ $device ][ $type ][ $side ] ?? '';
					$value     = lacadev_block_normalize_spacing_value( $raw_value );
					if ( '' === $value ) {
						continue;
					}

					$var_name      = $prefix . '-' . $type . '-' . $side;
					$device_suffix = 'desktop' === $device ? '' : '-' . $device;
					$style_vars[]  = $var_name . $device_suffix . ':' . $value;
				}
			}
		}

		if ( ! empty( $style_vars ) ) {
			return $style_vars;
		}

		$legacy_map = [
			'marginTop' => 'margin-top',
			'marginBottom' => 'margin-bottom',
			'paddingTop' => 'padding-top',
			'paddingBottom' => 'padding-bottom',
		];

		foreach ( $legacy_map as $attr_key => $css_suffix ) {
			if ( array_key_exists( $attr_key, $raw_attrs ) ) {
				$style_vars[] = $prefix . '-' . $css_suffix . ':' . intval( $attributes[ $attr_key ] ?? 0 ) . 'px';
			}
		}

		return $style_vars;
	}
}

if ( ! function_exists( 'lacadev_block_get_wrapper_attributes' ) ) {
	function lacadev_block_get_wrapper_attributes(
		string $class_name,
		array $attributes,
		string $prefix,
		array $raw_attrs = []
	): string {
		$wrapper_args = [ 'class' => $class_name ];
		$style_vars   = lacadev_block_get_spacing_style_vars(
			$attributes,
			$prefix,
			$raw_attrs
		);

		if ( ! empty( $style_vars ) ) {
			$wrapper_args['style'] = implode( ';', $style_vars ) . ';';
		}

		return get_block_wrapper_attributes( $wrapper_args );
	}
}

if ( ! function_exists( 'lacadev_block_render_section_header' ) ) {
	function lacadev_block_render_section_header(
		array $attributes,
		string $wrapper_class = 'laca-block-section-header',
		string $heading_class = '',
		string $subheading_class = ''
	): string {
		$heading    = sanitize_text_field( $attributes['heading'] ?? '' );
		$subheading = sanitize_text_field( $attributes['subheading'] ?? '' );

		if ( '' === $heading && '' === $subheading ) {
			return '';
		}

		$heading_tag = lacadev_block_normalize_heading_tag(
			sanitize_key( $attributes['headingTag'] ?? 'h2' )
		);
		$heading_align = lacadev_block_normalize_text_align(
			sanitize_key( $attributes['headingAlign'] ?? 'left' )
		);
		$subheading_align = lacadev_block_normalize_text_align(
			sanitize_key( $attributes['subheadingAlign'] ?? 'left' )
		);
		$heading_color = sanitize_hex_color( $attributes['headingColor'] ?? '#111111' ) ?: '#111111';
		$subheading_color = sanitize_hex_color( $attributes['subheadingColor'] ?? '#6b7280' ) ?: '#6b7280';

		ob_start();
		?>
		<header class="<?php echo esc_attr( trim( $wrapper_class ) ); ?>">
			<?php if ( '' !== $subheading ) : ?>
				<p
					class="<?php echo esc_attr( trim( 'laca-block-section-header__subheading ' . $subheading_class ) ); ?>"
					style="text-align:<?php echo esc_attr( $subheading_align ); ?>;color:<?php echo esc_attr( $subheading_color ); ?>;"
				>
					<?php echo esc_html( $subheading ); ?>
				</p>
			<?php endif; ?>

			<?php if ( '' !== $heading ) : ?>
				<<?php echo esc_attr( $heading_tag ); ?>
					class="<?php echo esc_attr( trim( 'laca-block-section-header__heading ' . $heading_class ) ); ?>"
					style="text-align:<?php echo esc_attr( $heading_align ); ?>;color:<?php echo esc_attr( $heading_color ); ?>;"
				>
					<?php echo esc_html( $heading ); ?>
				</<?php echo esc_attr( $heading_tag ); ?>>
			<?php endif; ?>
		</header>
		<?php

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'lacadev_block_render_button' ) ) {
	function lacadev_block_render_button(
		string $label,
		string $url,
		string $variant = 'primary',
		bool $new_tab = false
	): string {
		$label = trim( $label );
		$url   = trim( $url );

		if ( '' === $label || '' === $url ) {
			return '';
		}

		$classes = trim( 'laca-block-button laca-block-button--' . sanitize_html_class( $variant ) );
		$target  = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';

		return sprintf(
			'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
			esc_attr( $classes ),
			esc_url( $url ),
			$target,
			esc_html( $label )
		);
	}
}
