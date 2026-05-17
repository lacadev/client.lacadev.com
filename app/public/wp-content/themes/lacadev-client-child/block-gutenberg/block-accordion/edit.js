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