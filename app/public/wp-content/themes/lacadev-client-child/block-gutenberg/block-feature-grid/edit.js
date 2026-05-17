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