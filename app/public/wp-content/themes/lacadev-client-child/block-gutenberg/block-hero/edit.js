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