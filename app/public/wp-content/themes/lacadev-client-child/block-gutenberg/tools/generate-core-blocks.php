<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function ensure_dir(string $path): void {
	if (!is_dir($path)) {
		mkdir($path, 0777, true);
	}
}

function write_file(string $path, string $contents): void {
	ensure_dir(dirname($path));
	file_put_contents($path, $contents);
}

function spacing_default(): array {
	return [
		'desktop' => [
			'margin' => [ 'top' => '', 'left' => '', 'bottom' => '', 'right' => '' ],
			'padding' => [ 'top' => '', 'left' => '', 'bottom' => '', 'right' => '' ],
		],
		'tablet' => [
			'margin' => [ 'top' => '', 'left' => '', 'bottom' => '', 'right' => '' ],
			'padding' => [ 'top' => '', 'left' => '', 'bottom' => '', 'right' => '' ],
		],
		'mobile' => [
			'margin' => [ 'top' => '', 'left' => '', 'bottom' => '', 'right' => '' ],
			'padding' => [ 'top' => '', 'left' => '', 'bottom' => '', 'right' => '' ],
		],
	];
}

function common_attributes(array $extra = []): array {
	$base = [
		'bgColor' => [ 'type' => 'string', 'default' => '#ffffff' ],
		'bgOpacity' => [ 'type' => 'number', 'default' => 100 ],
		'__isPreview' => [ 'type' => 'boolean', 'default' => false ],
		'heading' => [ 'type' => 'string', 'default' => '' ],
		'subheading' => [ 'type' => 'string', 'default' => '' ],
		'headingTag' => [
			'type' => 'string',
			'default' => 'h2',
			'enum' => [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ],
		],
		'headingAlign' => [
			'type' => 'string',
			'default' => 'left',
			'enum' => [ 'left', 'center', 'right' ],
		],
		'subheadingAlign' => [
			'type' => 'string',
			'default' => 'left',
			'enum' => [ 'left', 'center', 'right' ],
		],
		'headingColor' => [ 'type' => 'string', 'default' => '#111111' ],
		'subheadingColor' => [ 'type' => 'string', 'default' => '#6b7280' ],
		'spacing' => [ 'type' => 'object', 'default' => spacing_default() ],
		'marginTop' => [ 'type' => 'number', 'default' => 0 ],
		'marginBottom' => [ 'type' => 'number', 'default' => 0 ],
		'paddingTop' => [ 'type' => 'number', 'default' => 60 ],
		'paddingBottom' => [ 'type' => 'number', 'default' => 55 ],
	];

	return array_merge($base, $extra);
}

function index_js(): string {
	return <<<'JS'
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: Save,
} );
JS;
}

function save_js(bool $with_inner_blocks = false): string {
	if ($with_inner_blocks) {
		return <<<'JS'
import { InnerBlocks } from '@wordpress/block-editor';

export default function save() {
	return <InnerBlocks.Content />;
}
JS;
	}

	return <<<'JS'
export default function save() {
	return null;
}
JS;
}

function write_preview_png(string $path, string $title, string $subtitle, string $hex): void {
	$image = imagecreatetruecolor(1200, 900);
	[$r, $g, $b] = sscanf($hex, "#%02x%02x%02x");
	$bg = imagecolorallocate($image, $r, $g, $b);
	$bg2 = imagecolorallocate($image, min(255, $r + 22), min(255, $g + 18), min(255, $b + 14));
	$white = imagecolorallocate($image, 255, 255, 255);
	$soft = imagecolorallocate($image, 224, 231, 255);
	$dark = imagecolorallocatealpha($image, 0, 0, 0, 70);

	for ($y = 0; $y < 900; $y++) {
		$ratio = $y / 900;
		$rr = (int) round($r + (($bg2 >> 16 & 0xFF) - $r) * $ratio);
		$gg = (int) round($g + (($bg2 >> 8 & 0xFF) - $g) * $ratio);
		$bb = (int) round($b + (($bg2 & 0xFF) - $b) * $ratio);
		$lineColor = imagecolorallocate($image, $rr, $gg, $bb);
		imageline($image, 0, $y, 1200, $y, $lineColor);
	}

	imagefilledrectangle($image, 90, 90, 1110, 810, $dark);
	imagefilledellipse($image, 980, 170, 160, 160, imagecolorallocatealpha($image, 255, 255, 255, 110));
	imagefilledellipse($image, 250, 690, 220, 220, imagecolorallocatealpha($image, 255, 255, 255, 115));

	imagestring($image, 5, 130, 150, 'LacaDev Core Block', $soft);
	imagestring($image, 5, 130, 210, $title, $white);
	imagestring($image, 4, 130, 260, $subtitle, $soft);
	imagestring($image, 3, 130, 720, 'Shared config + preview + dynamic render scaffold', $soft);

	imagepng($image, $path);
	imagedestroy($image);
}

function block_json(
	string $name,
	string $title,
	string $icon,
	string $description,
	array $attributes
): string {
	return json_encode(
		[
			'$schema' => 'https://schemas.wp.org/trunk/block.json',
			'apiVersion' => 3,
			'name' => $name,
			'version' => '1.0.0',
			'title' => $title,
			'category' => 'lacadev-blocks',
			'icon' => $icon,
			'description' => $description,
			'supports' => [
				'html' => false,
				'anchor' => true,
			],
			'attributes' => $attributes,
			'textdomain' => 'lacadev',
			'render' => 'file:./render.php',
			'example' => [
				'viewportWidth' => 800,
				'attributes' => [
					'__isPreview' => true,
				],
			],
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
	);
}

function write_block_files(
	string $slug,
	string $title,
	string $subtitle,
	string $accent,
	string $icon,
	string $description,
	array $attributes,
	string $edit,
	string $render,
	string $style
): void {
	global $root;

	$dir = $root . '/' . $slug;

	write_file($dir . '/block.json', block_json('lacadev/' . str_replace('block-', '', $slug), $title, $icon, $description, $attributes));
	write_file($dir . '/index.js', index_js());
	write_file($dir . '/save.js', save_js(false));
	write_file($dir . '/edit.js', $edit);
	write_file($dir . '/render.php', $render);
	write_file($dir . '/style.scss', $style);
	write_preview_png($dir . '/preview.png', $title, $subtitle, $accent);
}

write_block_files(
	'block-section-header',
	'Section Header',
	'Tiêu đề section tái sử dụng',
	'#1d4ed8',
	'heading',
	'Hiển thị tiêu đề và phụ đề chung theo style chuẩn của theme',
	common_attributes([
		'maxWidth' => [
			'type' => 'string',
			'default' => 'normal',
			'enum' => [ 'narrow', 'normal', 'wide' ],
		],
		'showDivider' => [ 'type' => 'boolean', 'default' => false ],
		'dividerColor' => [ 'type' => 'string', 'default' => '#dbe3f0' ],
	]),
	<<<'JS'
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { SelectControl, ToggleControl, ColorPalette } from '@wordpress/components';
import {
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	useInserterPreview,
} from '../utils';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#ffffff',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		maxWidth,
		showDivider,
		dividerColor,
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-section-header-block is-width-${ maxWidth }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-section-header' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Section Header', 'lacadev' ) }
				title={ __( 'Tieu de section dung chung', 'lacadev' ) }
				columns={ 1 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình header"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề section"
					subtitleLabel="Phụ đề section"
					configChildren={
						<>
							<SelectControl
								label={ __( 'Chiều rộng', 'lacadev' ) }
								value={ maxWidth }
								options={ [
									{ label: __( 'Narrow', 'lacadev' ), value: 'narrow' },
									{ label: __( 'Normal', 'lacadev' ), value: 'normal' },
									{ label: __( 'Wide', 'lacadev' ), value: 'wide' },
								] }
								onChange={ ( value ) => setAttributes( { maxWidth: value } ) }
							/>
							<ToggleControl
								label={ __( 'Hiển thị divider', 'lacadev' ) }
								checked={ showDivider }
								onChange={ ( value ) => setAttributes( { showDivider: value } ) }
							/>
							{ showDivider ? (
								<ColorPalette
									value={ dividerColor }
									onChange={ ( value ) =>
										setAttributes( { dividerColor: value || '#dbe3f0' } )
									}
								/>
							) : null }
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<BlockSectionHeaderPreview
					wrapperClassName="laca-block-section-header laca-section-header-block__inner"
					heading={ heading }
					subheading={ subheading }
					headingTag={ headingTag }
					headingAlign={ headingAlign }
					subheadingAlign={ subheadingAlign }
					headingColor={ headingColor }
					subheadingColor={ subheadingColor }
				/>
				{ showDivider ? (
					<div
						className="laca-section-header-block__divider"
						style={ { backgroundColor: dividerColor } }
					/>
				) : null }
			</div>
		</>
	);
}
JS,
	<<<'PHP'
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
PHP,
	<<<'SCSS'
@use "../common/section-header";

.laca-section-header-block {
	&__surface {
		padding: 1rem 0;
	}

	&__inner {
		margin: 0 auto;
	}

	&__divider {
		width: 100%;
		max-width: 12rem;
		height: 2px;
		margin: 1.4rem auto 0;
		border-radius: 999px;
	}

	&.is-width-narrow &__inner {
		max-width: 40rem;
	}

	&.is-width-normal &__inner {
		max-width: 56rem;
	}

	&.is-width-wide &__inner {
		max-width: 72rem;
	}
}
SCSS
);

write_block_files(
	'block-cta',
	'CTA Block',
	'Khối kêu gọi hành động chung',
	'#0f766e',
	'megaphone',
	'Khối CTA với tiêu đề, mô tả ngắn và 2 nút hành động',
	common_attributes([
		'description' => [ 'type' => 'string', 'default' => '' ],
		'buttonAlign' => [
			'type' => 'string',
			'default' => 'left',
			'enum' => [ 'left', 'center', 'right' ],
		],
		'tone' => [
			'type' => 'string',
			'default' => 'light',
			'enum' => [ 'light', 'dark', 'accent' ],
		],
		'primaryLabel' => [ 'type' => 'string', 'default' => '' ],
		'primaryUrl' => [ 'type' => 'string', 'default' => '' ],
		'primaryNewTab' => [ 'type' => 'boolean', 'default' => false ],
		'secondaryLabel' => [ 'type' => 'string', 'default' => '' ],
		'secondaryUrl' => [ 'type' => 'string', 'default' => '' ],
		'secondaryNewTab' => [ 'type' => 'boolean', 'default' => false ],
	]),
	<<<'JS'
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import {
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	useInserterPreview,
} from '../utils';

function ActionButtonPreview( { label = '', variant = 'primary' } ) {
	if ( ! label ) {
		return null;
	}

	return <span className={ `laca-block-button laca-block-button--${ variant }` }>{ label }</span>;
}

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#f8fafc',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		description,
		buttonAlign,
		tone,
		primaryLabel,
		secondaryLabel,
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-cta-block is-tone-${ tone }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-cta' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'CTA', 'lacadev' ) }
				title={ __( 'Call to action block', 'lacadev' ) }
				columns={ 1 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình CTA"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề CTA"
					subtitleLabel="Nhãn nhỏ phía trên"
					configChildren={
						<>
							<TextareaControl
								label={ __( 'Mô tả', 'lacadev' ) }
								value={ description }
								onChange={ ( value ) => setAttributes( { description: value } ) }
							/>
							<SelectControl
								label={ __( 'Canh nút', 'lacadev' ) }
								value={ buttonAlign }
								options={ [
									{ label: __( 'Trái', 'lacadev' ), value: 'left' },
									{ label: __( 'Giữa', 'lacadev' ), value: 'center' },
									{ label: __( 'Phải', 'lacadev' ), value: 'right' },
								] }
								onChange={ ( value ) => setAttributes( { buttonAlign: value } ) }
							/>
							<SelectControl
								label={ __( 'Tone khối', 'lacadev' ) }
								value={ tone }
								options={ [
									{ label: __( 'Light', 'lacadev' ), value: 'light' },
									{ label: __( 'Dark', 'lacadev' ), value: 'dark' },
									{ label: __( 'Accent', 'lacadev' ), value: 'accent' },
								] }
								onChange={ ( value ) => setAttributes( { tone: value } ) }
							/>
							<TextControl
								label={ __( 'Nút chính', 'lacadev' ) }
								value={ normalizedAttributes.primaryLabel || '' }
								onChange={ ( value ) => setAttributes( { primaryLabel: value } ) }
							/>
							<TextControl
								label={ __( 'Link nút chính', 'lacadev' ) }
								value={ normalizedAttributes.primaryUrl || '' }
								onChange={ ( value ) => setAttributes( { primaryUrl: value } ) }
							/>
							<ToggleControl
								label={ __( 'Nút chính mở tab mới', 'lacadev' ) }
								checked={ normalizedAttributes.primaryNewTab }
								onChange={ ( value ) => setAttributes( { primaryNewTab: value } ) }
							/>
							<TextControl
								label={ __( 'Nút phụ', 'lacadev' ) }
								value={ normalizedAttributes.secondaryLabel || '' }
								onChange={ ( value ) => setAttributes( { secondaryLabel: value } ) }
							/>
							<TextControl
								label={ __( 'Link nút phụ', 'lacadev' ) }
								value={ normalizedAttributes.secondaryUrl || '' }
								onChange={ ( value ) => setAttributes( { secondaryUrl: value } ) }
							/>
							<ToggleControl
								label={ __( 'Nút phụ mở tab mới', 'lacadev' ) }
								checked={ normalizedAttributes.secondaryNewTab }
								onChange={ ( value ) => setAttributes( { secondaryNewTab: value } ) }
							/>
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="laca-cta-block__inner">
					<BlockSectionHeaderPreview
						wrapperClassName="laca-block-section-header laca-cta-block__header"
						heading={ heading }
						subheading={ subheading }
						headingTag={ headingTag }
						headingAlign={ headingAlign }
						subheadingAlign={ subheadingAlign }
						headingColor={ headingColor }
						subheadingColor={ subheadingColor }
					/>
					{ description ? (
						<p className="laca-cta-block__description" style={ { textAlign: buttonAlign } }>
							{ description }
						</p>
					) : null }
					<div className={ `laca-cta-block__actions is-align-${ buttonAlign }` }>
						<ActionButtonPreview label={ primaryLabel } variant="primary" />
						<ActionButtonPreview label={ secondaryLabel } variant="secondary" />
					</div>
				</div>
			</div>
		</>
	);
}
JS,
	<<<'PHP'
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
PHP,
	<<<'SCSS'
@use "../common/buttons";
@use "../common/section-header";

.laca-cta-block {
	&__inner {
		padding: 2rem;
		border-radius: 1.5rem;
	}

	&__description {
		max-width: 44rem;
		margin: 0 auto;
		font-size: 1rem;
		line-height: 1.7;
		color: #475569;
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.85rem;
		margin-top: 1.5rem;

		&.is-align-left {
			justify-content: flex-start;
		}

		&.is-align-center {
			justify-content: center;
		}

		&.is-align-right {
			justify-content: flex-end;
		}
	}

	&.is-tone-dark &__inner {
		background: #0f172a !important;
	}

	&.is-tone-dark &__description,
	&.is-tone-dark .laca-block-section-header__heading,
	&.is-tone-dark .laca-block-section-header__subheading {
		color: #fff !important;
	}

	&.is-tone-accent &__inner {
		background: linear-gradient(135deg, #0f766e, #155e75) !important;
	}

	&.is-tone-accent &__description,
	&.is-tone-accent .laca-block-section-header__heading,
	&.is-tone-accent .laca-block-section-header__subheading {
		color: #fff !important;
	}
}
SCSS
);

write_block_files(
	'block-hero',
	'Hero Block',
	'Hero section cho landing page',
	'#7c3aed',
	'cover-image',
	'Hero banner với media, mô tả và nhóm CTA',
	common_attributes([
		'badge' => [ 'type' => 'string', 'default' => '' ],
		'description' => [ 'type' => 'string', 'default' => '' ],
		'layout' => [ 'type' => 'string', 'default' => 'split', 'enum' => [ 'split', 'stacked' ] ],
		'mediaPosition' => [ 'type' => 'string', 'default' => 'right', 'enum' => [ 'left', 'right' ] ],
		'mediaUrl' => [ 'type' => 'string', 'default' => '' ],
		'mediaId' => [ 'type' => 'number', 'default' => 0 ],
		'mediaAlt' => [ 'type' => 'string', 'default' => '' ],
		'primaryLabel' => [ 'type' => 'string', 'default' => '' ],
		'primaryUrl' => [ 'type' => 'string', 'default' => '' ],
		'secondaryLabel' => [ 'type' => 'string', 'default' => '' ],
		'secondaryUrl' => [ 'type' => 'string', 'default' => '' ],
	]),
	<<<'JS'
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	useInserterPreview,
} from '../utils';

function ButtonChip( { label = '', variant = 'primary' } ) {
	if ( ! label ) {
		return null;
	}

	return <span className={ `laca-block-button laca-block-button--${ variant }` }>{ label }</span>;
}

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#0f172a',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		badge,
		description,
		layout,
		mediaPosition,
		mediaUrl,
		mediaAlt,
		primaryLabel,
		secondaryLabel,
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-hero-block is-layout-${ layout } is-media-${ mediaPosition }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-hero' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Hero', 'lacadev' ) }
				title={ __( 'Hero banner cho landing page', 'lacadev' ) }
				columns={ 2 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình hero"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề hero"
					subtitleLabel="Phụ đề hero"
					configChildren={
						<>
							<TextControl
								label={ __( 'Badge nhỏ', 'lacadev' ) }
								value={ badge }
								onChange={ ( value ) => setAttributes( { badge: value } ) }
							/>
							<TextareaControl
								label={ __( 'Mô tả', 'lacadev' ) }
								value={ description }
								onChange={ ( value ) => setAttributes( { description: value } ) }
							/>
							<SelectControl
								label={ __( 'Layout', 'lacadev' ) }
								value={ layout }
								options={ [
									{ label: __( 'Split', 'lacadev' ), value: 'split' },
									{ label: __( 'Stacked', 'lacadev' ), value: 'stacked' },
								] }
								onChange={ ( value ) => setAttributes( { layout: value } ) }
							/>
							<SelectControl
								label={ __( 'Vị trí media', 'lacadev' ) }
								value={ mediaPosition }
								options={ [
									{ label: __( 'Media trái', 'lacadev' ), value: 'left' },
									{ label: __( 'Media phải', 'lacadev' ), value: 'right' },
								] }
								onChange={ ( value ) => setAttributes( { mediaPosition: value } ) }
							/>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={ ( media ) =>
										setAttributes( {
											mediaId: media.id,
											mediaUrl: media.url,
											mediaAlt: media.alt || '',
										} )
									}
									allowedTypes={ [ 'image' ] }
									value={ normalizedAttributes.mediaId }
									render={ ( { open } ) => (
										<Button variant="secondary" onClick={ open }>
											{ mediaUrl ? __( 'Thay ảnh hero', 'lacadev' ) : __( 'Chọn ảnh hero', 'lacadev' ) }
										</Button>
									) }
								/>
							</MediaUploadCheck>
							<TextControl
								label={ __( 'Nút chính', 'lacadev' ) }
								value={ normalizedAttributes.primaryLabel || '' }
								onChange={ ( value ) => setAttributes( { primaryLabel: value } ) }
							/>
							<TextControl
								label={ __( 'Link nút chính', 'lacadev' ) }
								value={ normalizedAttributes.primaryUrl || '' }
								onChange={ ( value ) => setAttributes( { primaryUrl: value } ) }
							/>
							<TextControl
								label={ __( 'Nút phụ', 'lacadev' ) }
								value={ normalizedAttributes.secondaryLabel || '' }
								onChange={ ( value ) => setAttributes( { secondaryLabel: value } ) }
							/>
							<TextControl
								label={ __( 'Link nút phụ', 'lacadev' ) }
								value={ normalizedAttributes.secondaryUrl || '' }
								onChange={ ( value ) => setAttributes( { secondaryUrl: value } ) }
							/>
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="laca-hero-block__inner">
					<div className="laca-hero-block__content">
						{ badge ? <span className="laca-hero-block__badge">{ badge }</span> : null }
						<BlockSectionHeaderPreview
							wrapperClassName="laca-block-section-header laca-hero-block__header"
							heading={ heading }
							subheading={ subheading }
							headingTag={ headingTag }
							headingAlign={ headingAlign }
							subheadingAlign={ subheadingAlign }
							headingColor={ headingColor }
							subheadingColor={ subheadingColor }
						/>
						{ description ? <p className="laca-hero-block__description">{ description }</p> : null }
						<div className="laca-hero-block__actions">
							<ButtonChip label={ primaryLabel } variant="light" />
							<ButtonChip label={ secondaryLabel } variant="secondary" />
						</div>
					</div>
					<div className="laca-hero-block__media">
						{ mediaUrl ? (
							<img src={ mediaUrl } alt={ mediaAlt || heading || 'Hero media' } />
						) : (
							<div className="laca-hero-block__media-placeholder">
								{ __( 'Hero media preview', 'lacadev' ) }
							</div>
						) }
					</div>
				</div>
			</div>
		</>
	);
}
JS,
	<<<'PHP'
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
PHP,
	<<<'SCSS'
@use "../common/buttons";
@use "../common/section-header";

.laca-hero-block {
	color: #fff;

	&__inner {
		display: grid;
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 2rem;
		align-items: center;
		padding: 2rem;
		border-radius: 1.75rem;
		overflow: hidden;
	}

	&.is-layout-stacked &__inner {
		grid-template-columns: 1fr;
	}

	&.is-media-left &__media {
		order: -1;
	}

	&__badge {
		display: inline-flex;
		padding: 0.45rem 0.8rem;
		border-radius: 999px;
		background: rgba(255, 255, 255, 0.14);
		font-size: 0.78rem;
		font-weight: 700;
	}

	&__description {
		max-width: 38rem;
		font-size: 1.05rem;
		line-height: 1.75;
		color: rgba(255, 255, 255, 0.84);
	}

	&__actions {
		display: flex;
		flex-wrap: wrap;
		gap: 0.9rem;
		margin-top: 1.5rem;
	}

	&__media img,
	&__media-placeholder {
		display: block;
		width: 100%;
		min-height: 22rem;
		border-radius: 1.35rem;
		object-fit: cover;
		background: rgba(255, 255, 255, 0.08);
	}

	&__media-placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		color: rgba(255, 255, 255, 0.65);
	}
}
SCSS
);

write_block_files(
	'block-feature-grid',
	'Feature Grid',
	'Lưới tính năng/dịch vụ',
	'#b45309',
	'screenoptions',
	'Hiển thị danh sách tính năng theo dạng grid với cấu hình card',
	common_attributes([
		'columns' => [ 'type' => 'string', 'default' => '3', 'enum' => [ '2', '3', '4' ] ],
		'cardTone' => [ 'type' => 'string', 'default' => 'soft', 'enum' => [ 'soft', 'outline', 'dark' ] ],
		'items' => [
			'type' => 'array',
			'default' => [
				[ 'kicker' => '01', 'title' => 'Feature 1', 'text' => 'Mo ta ngan cho feature 1.' ],
				[ 'kicker' => '02', 'title' => 'Feature 2', 'text' => 'Mo ta ngan cho feature 2.' ],
				[ 'kicker' => '03', 'title' => 'Feature 3', 'text' => 'Mo ta ngan cho feature 3.' ],
			],
		],
	]),
	<<<'JS'
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	appendArrayItem,
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	patchArrayItem,
	removeArrayItem,
	useInserterPreview,
} from '../utils';

const createItem = ( index = 0 ) => ( {
	kicker: `0${ index + 1 }`,
	title: `Feature ${ index + 1 }`,
	text: 'Mo ta ngan cho feature.',
} );

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#ffffff',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		columns,
		cardTone,
		items = [],
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-feature-grid-block is-tone-${ cardTone } columns-${ columns }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-feature-grid' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Feature Grid', 'lacadev' ) }
				title={ __( 'Danh sach tinh nang dang grid', 'lacadev' ) }
				columns={ 3 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình grid"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề feature grid"
					subtitleLabel="Phụ đề feature grid"
					configChildren={
						<>
							<SelectControl
								label={ __( 'Số cột', 'lacadev' ) }
								value={ columns }
								options={ [
									{ label: '2', value: '2' },
									{ label: '3', value: '3' },
									{ label: '4', value: '4' },
								] }
								onChange={ ( value ) => setAttributes( { columns: value } ) }
							/>
							<SelectControl
								label={ __( 'Tone card', 'lacadev' ) }
								value={ cardTone }
								options={ [
									{ label: __( 'Soft', 'lacadev' ), value: 'soft' },
									{ label: __( 'Outline', 'lacadev' ), value: 'outline' },
									{ label: __( 'Dark', 'lacadev' ), value: 'dark' },
								] }
								onChange={ ( value ) => setAttributes( { cardTone: value } ) }
							/>
							<PanelBody title={ __( 'Danh sách item', 'lacadev' ) } initialOpen={ false }>
								{ items.map( ( item, index ) => (
									<div key={ `feature-item-${ index }` } className="laca-editor-group">
										<TextControl
											label={ __( 'Nhãn nhỏ', 'lacadev' ) }
											value={ item.kicker || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { kicker: value } ) } )
											}
										/>
										<TextControl
											label={ __( 'Tiêu đề item', 'lacadev' ) }
											value={ item.title || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { title: value } ) } )
											}
										/>
										<TextareaControl
											label={ __( 'Mô tả item', 'lacadev' ) }
											value={ item.text || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { text: value } ) } )
											}
										/>
										<Button
											variant="link"
											isDestructive
											onClick={ () => setAttributes( { items: removeArrayItem( items, index ) } ) }
										>
											{ __( 'Xoá item', 'lacadev' ) }
										</Button>
									</div>
								) ) }
								<Button
									variant="secondary"
									onClick={ () =>
										setAttributes( {
											items: appendArrayItem( items, createItem( items.length ), 6 ),
										} )
									}
								>
									{ __( 'Thêm item', 'lacadev' ) }
								</Button>
							</PanelBody>
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<BlockSectionHeaderPreview
					wrapperClassName="laca-block-section-header laca-feature-grid-block__header"
					heading={ heading }
					subheading={ subheading }
					headingTag={ headingTag }
					headingAlign={ headingAlign }
					subheadingAlign={ subheadingAlign }
					headingColor={ headingColor }
					subheadingColor={ subheadingColor }
				/>
				<div className={ `laca-feature-grid-block__grid columns-${ columns }` }>
					{ items.map( ( item, index ) => (
						<article key={ `feature-card-${ index }` } className="laca-feature-grid-block__card">
							{ item.kicker ? <span className="laca-feature-grid-block__kicker">{ item.kicker }</span> : null }
							<h3 className="laca-feature-grid-block__title">{ item.title }</h3>
							<p className="laca-feature-grid-block__text">{ item.text }</p>
						</article>
					) ) }
				</div>
			</div>
		</>
	);
}
JS,
	<<<'PHP'
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
PHP,
	<<<'SCSS'
@use "../common/section-header";

.laca-feature-grid-block {
	&__surface {
		padding: 1rem 0;
	}

	&__grid {
		display: grid;
		gap: 1rem;
	}

	&__grid.columns-2 {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	&__grid.columns-3 {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}

	&__grid.columns-4 {
		grid-template-columns: repeat(4, minmax(0, 1fr));
	}

	&__card {
		padding: 1.5rem;
		border-radius: 1.25rem;
		background: #f8fafc;
	}

	&__kicker {
		display: inline-flex;
		margin-bottom: 0.85rem;
		font-size: 0.76rem;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: #0f766e;
	}

	&__title {
		margin: 0 0 0.7rem;
		font-size: 1.15rem;
	}

	&__text {
		margin: 0;
		line-height: 1.7;
		color: #475569;
	}

	&.is-tone-outline &__card {
		background: #fff;
		border: 1px solid rgba(148, 163, 184, 0.3);
	}

	&.is-tone-dark &__card {
		background: #0f172a;
	}

	&.is-tone-dark &__title,
	&.is-tone-dark &__text {
		color: #fff;
	}
}
SCSS
);

write_block_files(
	'block-accordion',
	'Accordion Block',
	'FAQ / câu hỏi thường gặp',
	'#be123c',
	'editor-help',
	'Khối accordion/FAQ dùng chung cho landing page và trang dịch vụ',
	common_attributes([
		'openFirst' => [ 'type' => 'boolean', 'default' => true ],
		'tone' => [ 'type' => 'string', 'default' => 'soft', 'enum' => [ 'soft', 'outline' ] ],
		'items' => [
			'type' => 'array',
			'default' => [
				[ 'question' => 'Câu hỏi 1?', 'answer' => 'Câu trả lời mẫu cho item đầu tiên.' ],
				[ 'question' => 'Câu hỏi 2?', 'answer' => 'Câu trả lời mẫu cho item thứ hai.' ],
				[ 'question' => 'Câu hỏi 3?', 'answer' => 'Câu trả lời mẫu cho item thứ ba.' ],
			],
		],
	]),
	<<<'JS'
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import {
	appendArrayItem,
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	patchArrayItem,
	removeArrayItem,
	useInserterPreview,
} from '../utils';

const createItem = () => ( {
	question: 'Câu hỏi mới?',
	answer: 'Câu trả lời mới.',
} );

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#ffffff',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		openFirst,
		tone,
		items = [],
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-accordion-block is-tone-${ tone }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-accordion' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Accordion', 'lacadev' ) }
				title={ __( 'FAQ / accordion dung chung', 'lacadev' ) }
				columns={ 2 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình accordion"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề accordion"
					subtitleLabel="Phụ đề accordion"
					configChildren={
						<>
							<ToggleControl
								label={ __( 'Mở item đầu tiên', 'lacadev' ) }
								checked={ openFirst }
								onChange={ ( value ) => setAttributes( { openFirst: value } ) }
							/>
							<SelectControl
								label={ __( 'Tone', 'lacadev' ) }
								value={ tone }
								options={ [
									{ label: __( 'Soft', 'lacadev' ), value: 'soft' },
									{ label: __( 'Outline', 'lacadev' ), value: 'outline' },
								] }
								onChange={ ( value ) => setAttributes( { tone: value } ) }
							/>
							<PanelBody title={ __( 'Danh sách câu hỏi', 'lacadev' ) } initialOpen={ false }>
								{ items.map( ( item, index ) => (
									<div key={ `faq-item-${ index }` } className="laca-editor-group">
										<TextControl
											label={ __( 'Câu hỏi', 'lacadev' ) }
											value={ item.question || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { question: value } ) } )
											}
										/>
										<TextareaControl
											label={ __( 'Câu trả lời', 'lacadev' ) }
											value={ item.answer || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { answer: value } ) } )
											}
										/>
										<Button
											variant="link"
											isDestructive
											onClick={ () => setAttributes( { items: removeArrayItem( items, index ) } ) }
										>
											{ __( 'Xoá item', 'lacadev' ) }
										</Button>
									</div>
								) ) }
								<Button
									variant="secondary"
									onClick={ () =>
										setAttributes( {
											items: appendArrayItem( items, createItem(), 8 ),
										} )
									}
								>
									{ __( 'Thêm item', 'lacadev' ) }
								</Button>
							</PanelBody>
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<BlockSectionHeaderPreview
					wrapperClassName="laca-block-section-header laca-accordion-block__header"
					heading={ heading }
					subheading={ subheading }
					headingTag={ headingTag }
					headingAlign={ headingAlign }
					subheadingAlign={ subheadingAlign }
					headingColor={ headingColor }
					subheadingColor={ subheadingColor }
				/>
				<div className="laca-accordion-block__items">
					{ items.map( ( item, index ) => (
						<details
							key={ `accordion-preview-${ index }` }
							className="laca-accordion-block__item"
							open={ openFirst && index === 0 }
						>
							<summary>{ item.question }</summary>
							<p>{ item.answer }</p>
						</details>
					) ) }
				</div>
			</div>
		</>
	);
}
JS,
	<<<'PHP'
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
PHP,
	<<<'SCSS'
@use "../common/section-header";

.laca-accordion-block {
	&__surface {
		padding: 1rem 0;
	}

	&__items {
		display: grid;
		gap: 0.9rem;
	}

	&__item {
		padding: 1.15rem 1.2rem;
		border-radius: 1rem;
		background: #f8fafc;

		summary {
			cursor: pointer;
			font-weight: 700;
		}

		p {
			margin: 0.9rem 0 0;
			line-height: 1.75;
			color: #475569;
		}
	}

	&.is-tone-outline &__item {
		background: #fff;
		border: 1px solid rgba(148, 163, 184, 0.35);
	}
}
SCSS
);

write_block_files(
	'block-testimonial',
	'Testimonial Block',
	'Khối review / testimonial',
	'#2563eb',
	'format-status',
	'Danh sách testimonial theo dạng grid dùng chung cho site giới thiệu',
	common_attributes([
		'columns' => [ 'type' => 'string', 'default' => '3', 'enum' => [ '1', '2', '3' ] ],
		'tone' => [ 'type' => 'string', 'default' => 'soft', 'enum' => [ 'soft', 'outline', 'dark' ] ],
		'items' => [
			'type' => 'array',
			'default' => [
				[ 'quote' => 'Dich vu rat tot va de hop tac.', 'name' => 'Nguyen Van A', 'role' => 'Founder' ],
				[ 'quote' => 'Tien do ro rang, giao tiep nhanh.', 'name' => 'Tran Thi B', 'role' => 'Marketing Lead' ],
				[ 'quote' => 'Website de dung va de mo rong ve sau.', 'name' => 'Le Van C', 'role' => 'Project Owner' ],
			],
		],
	]),
	<<<'JS'
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Button,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import {
	appendArrayItem,
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	patchArrayItem,
	removeArrayItem,
	useInserterPreview,
} from '../utils';

const createItem = () => ( {
	quote: 'Noi dung testimonial moi.',
	name: 'Ten khach hang',
	role: 'Vi tri / Cong ty',
} );

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#ffffff',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		columns,
		tone,
		items = [],
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-testimonial-block is-tone-${ tone } columns-${ columns }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-testimonial' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Testimonial', 'lacadev' ) }
				title={ __( 'Danh sach review khach hang', 'lacadev' ) }
				columns={ 3 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình testimonial"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề review"
					subtitleLabel="Phụ đề review"
					configChildren={
						<>
							<SelectControl
								label={ __( 'Số cột', 'lacadev' ) }
								value={ columns }
								options={ [
									{ label: '1', value: '1' },
									{ label: '2', value: '2' },
									{ label: '3', value: '3' },
								] }
								onChange={ ( value ) => setAttributes( { columns: value } ) }
							/>
							<SelectControl
								label={ __( 'Tone card', 'lacadev' ) }
								value={ tone }
								options={ [
									{ label: __( 'Soft', 'lacadev' ), value: 'soft' },
									{ label: __( 'Outline', 'lacadev' ), value: 'outline' },
									{ label: __( 'Dark', 'lacadev' ), value: 'dark' },
								] }
								onChange={ ( value ) => setAttributes( { tone: value } ) }
							/>
							<PanelBody title={ __( 'Danh sách review', 'lacadev' ) } initialOpen={ false }>
								{ items.map( ( item, index ) => (
									<div key={ `testimonial-item-${ index }` } className="laca-editor-group">
										<TextareaControl
											label={ __( 'Nội dung review', 'lacadev' ) }
											value={ item.quote || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { quote: value } ) } )
											}
										/>
										<TextControl
											label={ __( 'Tên người review', 'lacadev' ) }
											value={ item.name || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { name: value } ) } )
											}
										/>
										<TextControl
											label={ __( 'Vai trò', 'lacadev' ) }
											value={ item.role || '' }
											onChange={ ( value ) =>
												setAttributes( { items: patchArrayItem( items, index, { role: value } ) } )
											}
										/>
										<Button
											variant="link"
											isDestructive
											onClick={ () => setAttributes( { items: removeArrayItem( items, index ) } ) }
										>
											{ __( 'Xoá item', 'lacadev' ) }
										</Button>
									</div>
								) ) }
								<Button
									variant="secondary"
									onClick={ () =>
										setAttributes( { items: appendArrayItem( items, createItem(), 6 ) } )
									}
								>
									{ __( 'Thêm testimonial', 'lacadev' ) }
								</Button>
							</PanelBody>
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<BlockSectionHeaderPreview
					wrapperClassName="laca-block-section-header laca-testimonial-block__header"
					heading={ heading }
					subheading={ subheading }
					headingTag={ headingTag }
					headingAlign={ headingAlign }
					subheadingAlign={ subheadingAlign }
					headingColor={ headingColor }
					subheadingColor={ subheadingColor }
				/>
				<div className={ `laca-testimonial-block__grid columns-${ columns }` }>
					{ items.map( ( item, index ) => (
						<article key={ `testimonial-card-${ index }` } className="laca-testimonial-block__card">
							<p className="laca-testimonial-block__quote">“{ item.quote }”</p>
							<strong className="laca-testimonial-block__name">{ item.name }</strong>
							<span className="laca-testimonial-block__role">{ item.role }</span>
						</article>
					) ) }
				</div>
			</div>
		</>
	);
}
JS,
	<<<'PHP'
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
PHP,
	<<<'SCSS'
@use "../common/section-header";

.laca-testimonial-block {
	&__surface {
		padding: 1rem 0;
	}

	&__grid {
		display: grid;
		gap: 1rem;
	}

	&__grid.columns-1 {
		grid-template-columns: 1fr;
	}

	&__grid.columns-2 {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	&__grid.columns-3 {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}

	&__card {
		padding: 1.5rem;
		border-radius: 1.35rem;
		background: #f8fafc;
	}

	&__quote {
		margin: 0 0 1.2rem;
		font-size: 1rem;
		line-height: 1.8;
		color: #0f172a;
	}

	&__name {
		display: block;
		font-size: 1rem;
	}

	&__role {
		display: block;
		margin-top: 0.25rem;
		font-size: 0.92rem;
		color: #64748b;
	}

	&.is-tone-outline &__card {
		background: #fff;
		border: 1px solid rgba(148, 163, 184, 0.35);
	}

	&.is-tone-dark &__card {
		background: #0f172a;
	}

	&.is-tone-dark &__quote,
	&.is-tone-dark &__name,
	&.is-tone-dark &__role {
		color: #fff;
	}
}
SCSS
);

write_block_files(
	'block-post-list',
	'Post List',
	'Danh sách bài viết động',
	'#334155',
	'admin-post',
	'Hiển thị bài viết mới nhất theo post type và layout cơ bản',
	common_attributes([
		'postType' => [ 'type' => 'string', 'default' => 'post' ],
		'postsPerPage' => [ 'type' => 'number', 'default' => 3 ],
		'layout' => [ 'type' => 'string', 'default' => 'grid', 'enum' => [ 'grid', 'list' ] ],
		'showExcerpt' => [ 'type' => 'boolean', 'default' => true ],
		'showDate' => [ 'type' => 'boolean', 'default' => true ],
		'buttonLabel' => [ 'type' => 'string', 'default' => 'Đọc tiếp' ],
	]),
	<<<'JS'
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import {
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	useInserterPreview,
} from '../utils';

const previewPosts = [ 1, 2, 3, 4 ];

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#ffffff',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		postType,
		postsPerPage,
		layout,
		showExcerpt,
		showDate,
		buttonLabel,
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-post-list-block is-layout-${ layout }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-post-list' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Post List', 'lacadev' ) }
				title={ __( 'Danh sach bai viet dong', 'lacadev' ) }
				columns={ 3 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình danh sách bài viết"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề danh sách"
					subtitleLabel="Phụ đề danh sách"
					configChildren={
						<>
							<TextControl
								label={ __( 'Post type', 'lacadev' ) }
								value={ postType }
								onChange={ ( value ) => setAttributes( { postType: value } ) }
								help={ __( 'Nhập post, page hoặc custom post type slug.', 'lacadev' ) }
							/>
							<RangeControl
								label={ __( 'Số lượng bài', 'lacadev' ) }
								value={ postsPerPage }
								min={ 1 }
								max={ 8 }
								onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
							/>
							<SelectControl
								label={ __( 'Layout', 'lacadev' ) }
								value={ layout }
								options={ [
									{ label: __( 'Grid', 'lacadev' ), value: 'grid' },
									{ label: __( 'List', 'lacadev' ), value: 'list' },
								] }
								onChange={ ( value ) => setAttributes( { layout: value } ) }
							/>
							<ToggleControl
								label={ __( 'Hiển thị excerpt', 'lacadev' ) }
								checked={ showExcerpt }
								onChange={ ( value ) => setAttributes( { showExcerpt: value } ) }
							/>
							<ToggleControl
								label={ __( 'Hiển thị ngày đăng', 'lacadev' ) }
								checked={ showDate }
								onChange={ ( value ) => setAttributes( { showDate: value } ) }
							/>
							<TextControl
								label={ __( 'Nhãn nút đọc tiếp', 'lacadev' ) }
								value={ buttonLabel }
								onChange={ ( value ) => setAttributes( { buttonLabel: value } ) }
							/>
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<BlockSectionHeaderPreview
					wrapperClassName="laca-block-section-header laca-post-list-block__header"
					heading={ heading }
					subheading={ subheading }
					headingTag={ headingTag }
					headingAlign={ headingAlign }
					subheadingAlign={ subheadingAlign }
					headingColor={ headingColor }
					subheadingColor={ subheadingColor }
				/>
				<div className={ `laca-post-list-block__grid is-layout-${ layout }` }>
					{ previewPosts.slice( 0, postsPerPage ).map( ( item ) => (
						<article key={ `post-preview-${ item }` } className="laca-post-list-block__card">
							<span className="laca-post-list-block__type">{ postType }</span>
							<h3>Bài viết mẫu { item }</h3>
							{ showDate ? <time>16/05/2026</time> : null }
							{ showExcerpt ? (
								<p>Đây là preview tĩnh trong editor để mô phỏng cách danh sách bài viết sẽ hiển thị ngoài frontend.</p>
							) : null }
							<span className="laca-post-list-block__link">{ buttonLabel || 'Đọc tiếp' }</span>
						</article>
					) ) }
				</div>
			</div>
		</>
	);
}
JS,
	<<<'PHP'
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
PHP,
	<<<'SCSS'
@use "../common/section-header";

.laca-post-list-block {
	&__surface {
		padding: 1rem 0;
	}

	&__grid {
		display: grid;
		gap: 1rem;
	}

	&__grid.is-layout-grid {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}

	&__grid.is-layout-list {
		grid-template-columns: 1fr;
	}

	&__card {
		padding: 1.5rem;
		border-radius: 1.2rem;
		background: #f8fafc;

		h3 {
			margin: 0.55rem 0;
			font-size: 1.1rem;
		}

		p {
			margin: 0.55rem 0 0;
			line-height: 1.7;
			color: #475569;
		}

		time,
		a,
		span {
			display: inline-block;
		}
	}

	&__type {
		font-size: 0.75rem;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: #0f766e;
	}

	&__link {
		margin-top: 1rem;
		font-weight: 700;
		color: #0f172a;
		text-decoration: none;
	}
}
SCSS
);

echo "Core blocks generated.\n";
