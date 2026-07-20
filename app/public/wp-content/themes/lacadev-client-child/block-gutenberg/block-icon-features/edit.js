import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	TextControl,
	Button,
	ColorPicker,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../utils/preview';
import { IconInput } from '../utils/icon-input';
import previewImage from './preview.png';

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const {
		sectionTitle,
		items,

		iconColor,
		iconBgColor,
		titleColor,
		descColor,
		cardBgColor,
		sectionBgColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-lacadev-icon-features-block',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Why Choose Us', 'laca' ) }
				title={ attributes.heading || __( 'Tại sao nên chọn chúng tôi?', 'laca' ) }
				columns={ 4 }
				image={ previewImage }
			/>
		);
	}

	const updateItem = ( index, key, value ) => {
		const newItems = items.map( ( item, i ) =>
			i === index ? { ...item, [ key ]: value } : item
		);
		setAttributes( { items: newItems } );
	};

	const addItem = () => {
		setAttributes( {
			items: [
				...items,
				{
					icon: { type: 'svg', svg: '', imageId: 0, imageUrl: '' },
					title: 'Tiêu đề',
					description: 'Mô tả ngắn.',
				},
			],
		} );
	};

	const removeItem = ( index ) => {
		setAttributes( { items: items.filter( ( _, i ) => i !== index ) } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Nội dung chung', 'laca' ) }
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
					title={ __( 'Danh sách mục', 'laca' ) }
					initialOpen={ true }
				>
					{ items.map( ( item, index ) => (
						<div
							key={ index }
							style={ {
								borderBottom: '1px solid #ddd',
								marginBottom: 12,
								paddingBottom: 12,
							} }
						>
							<PanelRow>
								<IconInput
									icon={
										typeof item.icon === 'string' || ! item.icon
											? { type: 'svg', svg: '' }
											: item.icon
									}
									onChange={ ( v ) =>
										updateItem( index, 'icon', v )
									}
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={ __( 'Tiêu đề', 'laca' ) }
									value={ item.title }
									onChange={ ( v ) =>
										updateItem( index, 'title', v )
									}
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={ __( 'Mô tả', 'laca' ) }
									value={ item.description }
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
						{ __( '+ Thêm mục', 'laca' ) }
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
						{ __( 'Màu nền thẻ', 'laca' ) }
					</p>
					<ColorPicker
						color={ cardBgColor }
						onChange={ ( v ) =>
							setAttributes( { cardBgColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu nền vòng icon', 'laca' ) }
					</p>
					<ColorPicker
						color={ iconBgColor }
						onChange={ ( v ) =>
							setAttributes( { iconBgColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu icon', 'laca' ) }
					</p>
					<ColorPicker
						color={ iconColor }
						onChange={ ( v ) => setAttributes( { iconColor: v } ) }
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu tiêu đề', 'laca' ) }
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
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/icon-features-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
