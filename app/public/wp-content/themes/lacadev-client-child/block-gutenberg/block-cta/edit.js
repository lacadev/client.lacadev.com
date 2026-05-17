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