import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	TextControl,
	RangeControl,
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
		items,

		iconColor,
		numberColor,
		labelColor,
		cardBgColor,
		bgColor,
		pullUpOverlap,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-lacadev-stats-cards-block',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Clinic Stats', 'laca' ) }
				title={ attributes.heading || __( 'Thống kê phòng khám', 'laca' ) }
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
					number: '0',
					suffix: '+',
					label: 'LABEL',
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
					title={ __( 'Danh sách chỉ số', 'laca' ) }
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
									label={ __( 'Số', 'laca' ) }
									value={ item.number }
									onChange={ ( v ) =>
										updateItem( index, 'number', v )
									}
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={ __( 'Hậu tố', 'laca' ) }
									value={ item.suffix }
									onChange={ ( v ) =>
										updateItem( index, 'suffix', v )
									}
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={ __( 'Label', 'laca' ) }
									value={ item.label }
									onChange={ ( v ) =>
										updateItem( index, 'label', v )
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
						{ __( '+ Thêm chỉ số', 'laca' ) }
					</Button>
				</PanelBody>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p style={ { marginBottom: 4 } }>
						{ __( 'Màu icon', 'laca' ) }
					</p>
					<ColorPicker
						color={ iconColor }
						onChange={ ( v ) => setAttributes( { iconColor: v } ) }
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu số', 'laca' ) }
					</p>
					<ColorPicker
						color={ numberColor }
						onChange={ ( v ) =>
							setAttributes( { numberColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu label', 'laca' ) }
					</p>
					<ColorPicker
						color={ labelColor }
						onChange={ ( v ) => setAttributes( { labelColor: v } ) }
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
						{ __( 'Màu nền section', 'laca' ) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
						enableAlpha={ true }
					/>
					<RangeControl
						label={ __( 'Độ kéo lên (px)', 'laca' ) }
						help={ __(
							'Khoảng cách âm phía trên, giúp hàng thẻ đè lên section phía trước.',
							'laca'
						) }
						value={ pullUpOverlap }
						onChange={ ( v ) =>
							setAttributes( { pullUpOverlap: v } )
						}
						min={ 0 }
						max={ 100 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/stats-cards-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
