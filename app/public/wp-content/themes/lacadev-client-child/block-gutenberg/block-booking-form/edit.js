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
		sectionDescription,
		hotlineLabel,
		hotlineValue,
		addressLabel,
		addressValue,
		serviceOptions,
		submitButtonText,
		primaryColor,
		bgColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-lacadev-booking-form-block',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Booking Form', 'laca' ) }
				title={ attributes.heading || __( 'Đăng ký đặt hẹn', 'laca' ) }
				columns={ 2 }
				image={ previewImage }
			/>
		);
	}

	const updateItem = ( index, key, value ) => {
		const newItems = serviceOptions.map( ( item, i ) =>
			i === index ? { ...item, [ key ]: value } : item
		);
		setAttributes( { serviceOptions: newItems } );
	};

	const addItem = () => {
		setAttributes( {
			serviceOptions: [ ...serviceOptions, { label: 'Dịch vụ mới' } ],
		} );
	};

	const removeItem = ( index ) => {
		setAttributes( {
			serviceOptions: serviceOptions.filter( ( _, i ) => i !== index ),
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Nội dung', 'laca' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Tiêu đề', 'laca' ) }
						value={ sectionTitle }
						onChange={ ( v ) =>
							setAttributes( { sectionTitle: v } )
						}
					/>
					<TextareaControl
						label={ __( 'Mô tả', 'laca' ) }
						value={ sectionDescription }
						onChange={ ( v ) =>
							setAttributes( { sectionDescription: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Thông tin liên hệ', 'laca' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Label Hotline', 'laca' ) }
						value={ hotlineLabel }
						onChange={ ( v ) =>
							setAttributes( { hotlineLabel: v } )
						}
					/>
					<TextControl
						label={ __( 'Số Hotline', 'laca' ) }
						value={ hotlineValue }
						onChange={ ( v ) =>
							setAttributes( { hotlineValue: v } )
						}
					/>
					<TextControl
						label={ __( 'Label Địa chỉ', 'laca' ) }
						value={ addressLabel }
						onChange={ ( v ) =>
							setAttributes( { addressLabel: v } )
						}
					/>
					<TextControl
						label={ __( 'Địa chỉ', 'laca' ) }
						value={ addressValue }
						onChange={ ( v ) =>
							setAttributes( { addressValue: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Dịch vụ quan tâm', 'laca' ) }
					initialOpen={ false }
				>
					{ serviceOptions.map( ( item, index ) => (
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
									label={ __( 'Nhãn', 'laca' ) }
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
						{ __( '+ Thêm dịch vụ', 'laca' ) }
					</Button>
				</PanelBody>

				<PanelBody
					title={ __( 'Nút gửi', 'laca' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Nội dung nút', 'laca' ) }
						value={ submitButtonText }
						onChange={ ( v ) =>
							setAttributes( { submitButtonText: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p style={ { marginBottom: 4 } }>
						{ __( 'Màu chủ đạo', 'laca' ) }
					</p>
					<ColorPicker
						color={ primaryColor }
						onChange={ ( v ) =>
							setAttributes( { primaryColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu nền section', 'laca' ) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
						enableAlpha={ false }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/booking-form-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
