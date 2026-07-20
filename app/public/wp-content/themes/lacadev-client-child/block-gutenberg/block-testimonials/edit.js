import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	MediaUpload,
	MediaUploadCheck,
} from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	TextControl,
	TextareaControl,
	RangeControl,
	Button,
	ColorPicker,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../utils/preview';
import previewImage from './preview.png';

function ImagePicker( { imageUrl, imageId, onSelect, label } ) {
	return (
		<MediaUploadCheck>
			<MediaUpload
				onSelect={ onSelect }
				allowedTypes={ [ 'image' ] }
				value={ imageId }
				render={ ( { open } ) => (
					<div style={ { marginBottom: 8 } }>
						{ label && (
							<p
								style={ {
									fontSize: 11,
									color: '#888',
									marginBottom: 4,
								} }
							>
								{ label }
							</p>
						) }
						{ imageUrl && (
							<img
								src={ imageUrl }
								alt=""
								style={ {
									width: '100%',
									maxHeight: 80,
									objectFit: 'cover',
									marginBottom: 4,
									borderRadius: 4,
								} }
							/>
						) }
						<Button
							variant="secondary"
							onClick={ open }
							style={ { fontSize: 11 } }
						>
							{ imageUrl
								? __( 'Đổi ảnh', 'laca' )
								: __( 'Chọn ảnh', 'laca' ) }
						</Button>
					</div>
				) }
			/>
		</MediaUploadCheck>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const {
		sectionTitle,
		testimonials,
		starColor,
		quoteColor,
		nameColor,
		locationColor,
		cardBgColor,
		borderColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-lacadev-testimonials-block',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Testimonials', 'laca' ) }
				title={
					sectionTitle ||
					__( 'Khách hàng nói gì về chúng tôi?', 'laca' )
				}
				columns={ 3 }
				image={ previewImage }
			/>
		);
	}

	const updateTestimonial = ( index, key, value ) => {
		const newTestimonials = testimonials.map( ( item, i ) =>
			i === index ? { ...item, [ key ]: value } : item
		);
		setAttributes( { testimonials: newTestimonials } );
	};

	const addTestimonial = () => {
		setAttributes( {
			testimonials: [
				...testimonials,
				{
					avatarImageId: 0,
					avatarImageUrl: '',
					quote: '',
					name: '',
					location: '',
					rating: 5,
				},
			],
		} );
	};

	const removeTestimonial = ( index ) => {
		setAttributes( {
			testimonials: testimonials.filter( ( _, i ) => i !== index ),
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Chung', 'laca' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Tiêu đề Section', 'laca' ) }
						value={ sectionTitle || '' }
						onChange={ ( v ) =>
							setAttributes( { sectionTitle: v } )
						}
					/>
				</PanelBody>

				{ testimonials.map( ( testimonial, index ) => (
					<PanelBody
						key={ index }
						title={ `${ __( 'Đánh giá', 'laca' ) } ${
							index + 1
						}: ${ testimonial.name || '' }` }
						initialOpen={ index === 0 }
					>
						<ImagePicker
							label={ __( 'Ảnh đại diện', 'laca' ) }
							imageUrl={ testimonial.avatarImageUrl }
							imageId={ testimonial.avatarImageId }
							onSelect={ ( media ) => {
								updateTestimonial(
									index,
									'avatarImageId',
									media.id
								);
								updateTestimonial(
									index,
									'avatarImageUrl',
									media.url
								);
							} }
						/>
						<PanelRow>
							<TextareaControl
								label={ __( 'Nội dung đánh giá', 'laca' ) }
								value={ testimonial.quote }
								onChange={ ( v ) =>
									updateTestimonial( index, 'quote', v )
								}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={ __( 'Tên khách hàng', 'laca' ) }
								value={ testimonial.name }
								onChange={ ( v ) =>
									updateTestimonial( index, 'name', v )
								}
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={ __( 'Địa chỉ / vai trò', 'laca' ) }
								value={ testimonial.location }
								onChange={ ( v ) =>
									updateTestimonial( index, 'location', v )
								}
							/>
						</PanelRow>
						<RangeControl
							label={ __( 'Số sao đánh giá', 'laca' ) }
							value={ testimonial.rating }
							onChange={ ( v ) =>
								updateTestimonial( index, 'rating', v )
							}
							min={ 1 }
							max={ 5 }
						/>
						<Button
							isDestructive
							variant="secondary"
							onClick={ () => removeTestimonial( index ) }
							style={ { marginTop: 4 } }
						>
							{ __( 'Xóa', 'laca' ) }
						</Button>
					</PanelBody>
				) ) }
				<div style={ { padding: '8px 16px 16px' } }>
					<Button variant="primary" onClick={ addTestimonial }>
						{ __( '+ Thêm đánh giá', 'laca' ) }
					</Button>
				</div>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p style={ { marginBottom: 4 } }>
						{ __( 'Màu sao đánh giá', 'laca' ) }
					</p>
					<ColorPicker
						color={ starColor }
						onChange={ ( v ) => setAttributes( { starColor: v } ) }
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu nội dung trích dẫn', 'laca' ) }
					</p>
					<ColorPicker
						color={ quoteColor }
						onChange={ ( v ) =>
							setAttributes( { quoteColor: v } )
						}
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu tên khách hàng', 'laca' ) }
					</p>
					<ColorPicker
						color={ nameColor }
						onChange={ ( v ) => setAttributes( { nameColor: v } ) }
						enableAlpha={ false }
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu địa chỉ / vai trò', 'laca' ) }
					</p>
					<ColorPicker
						color={ locationColor }
						onChange={ ( v ) =>
							setAttributes( { locationColor: v } )
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
						{ __( 'Màu viền thẻ', 'laca' ) }
					</p>
					<ColorPicker
						color={ borderColor }
						onChange={ ( v ) =>
							setAttributes( { borderColor: v } )
						}
						enableAlpha={ false }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/testimonials-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
