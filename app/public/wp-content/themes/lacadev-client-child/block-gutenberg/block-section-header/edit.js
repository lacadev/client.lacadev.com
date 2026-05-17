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