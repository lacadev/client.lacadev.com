import {
	AlignmentToolbar,
	BlockControls,
	ColorPalette,
} from '@wordpress/block-editor';
import { BaseControl, PanelBody, SelectControl } from '@wordpress/components';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export const TEXT_ALIGN_OPTIONS = [ 'left', 'center', 'right' ];
export const HEADING_TAG_OPTIONS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ];

export function normalizeTextAlign( value = 'left' ) {
	return TEXT_ALIGN_OPTIONS.includes( value ) ? value : 'left';
}

export function normalizeHeadingTag( value = 'h2' ) {
	return HEADING_TAG_OPTIONS.includes( value ) ? value : 'h2';
}

export function getSectionHeaderInlineStyles( {
	headingAlign = 'left',
	subheadingAlign = 'left',
	headingColor = '#111111',
	subheadingColor = '#6b7280',
} = {} ) {
	return {
		heading: {
			textAlign: normalizeTextAlign( headingAlign ),
			color: headingColor || '#111111',
		},
		subheading: {
			textAlign: normalizeTextAlign( subheadingAlign ),
			color: subheadingColor || '#6b7280',
		},
	};
}

export function SectionHeaderToolbar( {
	headingAlign = 'left',
	subheadingAlign = 'left',
	setAttributes,
} ) {
	return (
		<BlockControls group="block">
			<AlignmentToolbar
				label={ __( 'Căn tiêu đề', 'lacadev' ) }
				value={ normalizeTextAlign( headingAlign ) }
				onChange={ ( value ) =>
					setAttributes( {
						headingAlign: normalizeTextAlign( value ),
					} )
				}
			/>
			<AlignmentToolbar
				label={ __( 'Căn sub tiêu đề', 'lacadev' ) }
				value={ normalizeTextAlign( subheadingAlign ) }
				onChange={ ( value ) =>
					setAttributes( {
						subheadingAlign: normalizeTextAlign( value ),
					} )
				}
			/>
		</BlockControls>
	);
}

export function SectionHeaderStylePanel( {
	panelTitle = 'Style tiêu đề',
	headingTag = 'h2',
	headingAlign = 'left',
	subheadingAlign = 'left',
	headingColor = '#111111',
	subheadingColor = '#6b7280',
	textdomain = 'lacadev',
	setAttributes,
} ) {
	return (
		<PanelBody title={ panelTitle } initialOpen={ false }>
			<SectionHeaderStyleFields
				headingTag={ headingTag }
				headingAlign={ headingAlign }
				subheadingAlign={ subheadingAlign }
				headingColor={ headingColor }
				subheadingColor={ subheadingColor }
				textdomain={ textdomain }
				setAttributes={ setAttributes }
			/>
		</PanelBody>
	);
}

export function SectionHeaderStyleFields( {
	headingTag = 'h2',
	headingAlign = 'left',
	subheadingAlign = 'left',
	headingColor = '#111111',
	subheadingColor = '#6b7280',
	textdomain = 'lacadev',
	setAttributes,
} ) {
	return (
		<>
			<SelectControl
				label={ __( 'Thẻ HTML tiêu đề', textdomain ) }
				value={ normalizeHeadingTag( headingTag ) }
				options={ HEADING_TAG_OPTIONS.map( ( tag ) => ( {
					label: tag.toUpperCase(),
					value: tag,
				} ) ) }
				onChange={ ( value ) =>
					setAttributes( {
						headingTag: normalizeHeadingTag( value ),
					} )
				}
			/>

			<BaseControl label={ __( 'Căn lề tiêu đề', textdomain ) }>
				<AlignmentToolbar
					value={ normalizeTextAlign( headingAlign ) }
					onChange={ ( value ) =>
						setAttributes( {
							headingAlign: normalizeTextAlign( value ),
						} )
					}
				/>
			</BaseControl>

			<BaseControl label={ __( 'Màu tiêu đề', textdomain ) }>
				<ColorPalette
					value={ headingColor }
					onChange={ ( value ) =>
						setAttributes( { headingColor: value || '#111111' } )
					}
				/>
			</BaseControl>

			<BaseControl label={ __( 'Căn lề sub tiêu đề', textdomain ) }>
				<AlignmentToolbar
					value={ normalizeTextAlign( subheadingAlign ) }
					onChange={ ( value ) =>
						setAttributes( {
							subheadingAlign: normalizeTextAlign( value ),
						} )
					}
				/>
			</BaseControl>

			<BaseControl label={ __( 'Màu sub tiêu đề', textdomain ) }>
				<ColorPalette
					value={ subheadingColor }
					onChange={ ( value ) =>
						setAttributes( { subheadingColor: value || '#6b7280' } )
					}
				/>
			</BaseControl>
		</>
	);
}

export function BlockSectionHeaderPreview( {
	heading = '',
	subheading = '',
	headingTag = 'h2',
	headingAlign = 'left',
	subheadingAlign = 'left',
	headingColor = '#111111',
	subheadingColor = '#6b7280',
	wrapperClassName = 'laca-block-section-header',
	headingClassName = '',
	subheadingClassName = '',
} ) {
	if ( ! heading && ! subheading ) {
		return null;
	}

	const styles = getSectionHeaderInlineStyles( {
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
	} );

	return (
		<div className={ wrapperClassName }>
			{ subheading ? (
				<p
					className={ `laca-block-section-header__subheading ${ subheadingClassName }`.trim() }
					style={ styles.subheading }
				>
					{ subheading }
				</p>
			) : null }

			{ heading
				? createElement(
					normalizeHeadingTag( headingTag ),
					{
						className:
							`laca-block-section-header__heading ${ headingClassName }`.trim(),
						style: styles.heading,
					},
					heading
				  )
				: null }
		</div>
	);
}
