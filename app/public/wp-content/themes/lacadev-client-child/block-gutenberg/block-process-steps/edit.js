import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	TextControl,
	TextareaControl,
	Button,
	ColorPicker,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../utils/preview';
import previewImage from './preview.png';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const {
		sectionTitle,
		steps,

		circleColor,
		circleTextColor,
		titleColor,
		descColor,
		lineColor,
		sectionBgColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-lacadev-process-steps-block',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Process Steps', 'laca' ) }
				title={ attributes.heading || sectionTitle }
				columns={ 3 }
				image={ previewImage }
			/>
		);
	}

	const updateItem = ( index, key, value ) => {
		const newSteps = steps.map( ( step, i ) =>
			i === index ? { ...step, [ key ]: value } : step
		);
		setAttributes( { steps: newSteps } );
	};

	const addItem = () => {
		setAttributes( {
			steps: [
				...steps,
				{ title: 'Tiêu đề bước', description: 'Mô tả bước.' },
			],
		} );
	};

	const removeItem = ( index ) => {
		setAttributes( { steps: steps.filter( ( _, i ) => i !== index ) } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Nội dung', 'laca' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Tiêu đề section', 'laca' ) }
						value={ sectionTitle }
						onChange={ ( v ) =>
							setAttributes( { sectionTitle: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Danh sách bước', 'laca' ) }
					initialOpen={ true }
				>
					{ steps.map( ( step, index ) => (
						<div
							key={ index }
							style={ {
								borderBottom: '1px solid #ddd',
								marginBottom: 12,
								paddingBottom: 12,
							} }
						>
							<PanelRow>
								<TextControl
									label={ __( 'Tiêu đề', 'laca' ) }
									value={ step.title }
									onChange={ ( v ) =>
										updateItem( index, 'title', v )
									}
								/>
							</PanelRow>
							<PanelRow>
								<TextareaControl
									label={ __( 'Mô tả', 'laca' ) }
									value={ step.description }
									onChange={ ( v ) =>
										updateItem( index, 'description', v )
									}
								/>
							</PanelRow>
							<Button
								isDestructive
								variant="secondary"
								onClick={ () => removeItem( index ) }
								style={ { marginTop: 4 } }
							>
								{ __( 'Xóa', 'laca' ) }
							</Button>
						</div>
					) ) }
					<Button variant="primary" onClick={ addItem }>
						{ __( '+ Thêm bước', 'laca' ) }
					</Button>
				</PanelBody>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p style={ { marginBottom: 4 } }>
						{ __( 'Màu nền section', 'laca' ) }
					</p>
					<ColorPicker
						color={ sectionBgColor }
						onChange={ ( v ) =>
							setAttributes( { sectionBgColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu nền vòng tròn số', 'laca' ) }
					</p>
					<ColorPicker
						color={ circleColor }
						onChange={ ( v ) =>
							setAttributes( { circleColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu chữ số', 'laca' ) }
					</p>
					<ColorPicker
						color={ circleTextColor }
						onChange={ ( v ) =>
							setAttributes( { circleTextColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu tiêu đề bước', 'laca' ) }
					</p>
					<ColorPicker
						color={ titleColor }
						onChange={ ( v ) =>
							setAttributes( { titleColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu mô tả', 'laca' ) }
					</p>
					<ColorPicker
						color={ descColor }
						onChange={ ( v ) => setAttributes( { descColor: v } ) }
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu đường nối (desktop)', 'laca' ) }
					</p>
					<ColorPicker
						color={ lineColor }
						onChange={ ( v ) => setAttributes( { lineColor: v } ) }
						enableAlpha={ false }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/process-steps-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
