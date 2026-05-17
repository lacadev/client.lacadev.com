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