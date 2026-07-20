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
		badgeText,
		headlineLine1,
		headlineLine2,
		checklistItems,
		primaryCtaText,
		primaryCtaLink,
		secondaryCtaText,
		secondaryCtaLink,
		avatarImageIds,
		avatarImageUrls,
		ratingText,
		heroImageId,
		heroImageUrl,
		primaryColor,
		charcoalColor,
		bgColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-lacadev-clinic-hero-block',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Clinic Hero', 'laca' ) }
				title={ headlineLine1 || __( 'Kiến tạo nụ cười', 'laca' ) }
				columns={ 1 }
				image={ previewImage }
			/>
		);
	}

	const updateChecklistItem = ( index, value ) => {
		const newItems = checklistItems.map( ( item, i ) =>
			i === index ? { ...item, text: value } : item
		);
		setAttributes( { checklistItems: newItems } );
	};

	const addChecklistItem = () => {
		setAttributes( {
			checklistItems: [ ...checklistItems, { text: '' } ],
		} );
	};

	const removeChecklistItem = ( index ) => {
		setAttributes( {
			checklistItems: checklistItems.filter( ( _, i ) => i !== index ),
		} );
	};

	const updateAvatar = ( index, media ) => {
		const newIds = [ ...avatarImageIds ];
		const newUrls = [ ...avatarImageUrls ];
		newIds[ index ] = media.id;
		newUrls[ index ] = media.url;
		setAttributes( { avatarImageIds: newIds, avatarImageUrls: newUrls } );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Badge & Headline', 'laca' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Badge', 'laca' ) }
						value={ badgeText }
						onChange={ ( v ) =>
							setAttributes( { badgeText: v } )
						}
					/>
					<TextControl
						label={ __( 'Headline dòng 1', 'laca' ) }
						value={ headlineLine1 }
						onChange={ ( v ) =>
							setAttributes( { headlineLine1: v } )
						}
					/>
					<TextControl
						label={ __(
							'Headline dòng 2 (màu primary)',
							'laca'
						) }
						value={ headlineLine2 }
						onChange={ ( v ) =>
							setAttributes( { headlineLine2: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Checklist', 'laca' ) }
					initialOpen={ false }
				>
					{ checklistItems.map( ( item, index ) => (
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
									label={ `${ __( 'Mục', 'laca' ) } ${
										index + 1
									}` }
									value={ item.text }
									onChange={ ( v ) =>
										updateChecklistItem( index, v )
									}
								/>
							</PanelRow>
							<Button
								isDestructive
								variant="secondary"
								onClick={ () => removeChecklistItem( index ) }
								style={ { marginTop: 4 } }
							>
								{ __( 'Xóa', 'laca' ) }
							</Button>
						</div>
					) ) }
					<Button variant="primary" onClick={ addChecklistItem }>
						{ __( '+ Thêm mục', 'laca' ) }
					</Button>
				</PanelBody>

				<PanelBody
					title={ __( 'Nút kêu gọi hành động (CTA)', 'laca' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'CTA chính - Nội dung', 'laca' ) }
						value={ primaryCtaText }
						onChange={ ( v ) =>
							setAttributes( { primaryCtaText: v } )
						}
					/>
					<TextControl
						label={ __( 'CTA chính - Link', 'laca' ) }
						value={ primaryCtaLink }
						onChange={ ( v ) =>
							setAttributes( { primaryCtaLink: v } )
						}
					/>
					<TextControl
						label={ __( 'CTA phụ - Nội dung', 'laca' ) }
						value={ secondaryCtaText }
						onChange={ ( v ) =>
							setAttributes( { secondaryCtaText: v } )
						}
					/>
					<TextControl
						label={ __( 'CTA phụ - Link', 'laca' ) }
						value={ secondaryCtaLink }
						onChange={ ( v ) =>
							setAttributes( { secondaryCtaLink: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Khách hàng & Đánh giá', 'laca' ) }
					initialOpen={ false }
				>
					{ [ 0, 1, 2 ].map( ( index ) => (
						<ImagePicker
							key={ index }
							label={ `${ __( 'Avatar', 'laca' ) } ${
								index + 1
							}` }
							imageUrl={ avatarImageUrls?.[ index ] }
							imageId={ avatarImageIds?.[ index ] }
							onSelect={ ( media ) =>
								updateAvatar( index, media )
							}
						/>
					) ) }
					<TextControl
						label={ __( 'Nội dung rating', 'laca' ) }
						value={ ratingText }
						onChange={ ( v ) =>
							setAttributes( { ratingText: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Ảnh Hero', 'laca' ) }
					initialOpen={ false }
				>
					<ImagePicker
						label={ __( 'Ảnh lớn bên phải', 'laca' ) }
						imageUrl={ heroImageUrl }
						imageId={ heroImageId }
						onSelect={ ( media ) =>
							setAttributes( {
								heroImageId: media.id,
								heroImageUrl: media.url,
							} )
						}
					/>
				</PanelBody>

				{ /* Panel: Giao diện */ }
				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p style={ { marginBottom: 4 } }>
						{ __( 'Màu nền section', 'laca' ) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
						enableAlpha={ false }
						defaultValue="#f2f4f2"
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu primary (headline dòng 2, icon, CTA)', 'laca' ) }
					</p>
					<ColorPicker
						color={ primaryColor }
						onChange={ ( v ) =>
							setAttributes( { primaryColor: v } )
						}
						enableAlpha={ false }
						defaultValue="#0d631b"
					/>
					<p style={ { marginTop: 12, marginBottom: 4 } }>
						{ __( 'Màu chữ headline (charcoal)', 'laca' ) }
					</p>
					<ColorPicker
						color={ charcoalColor }
						onChange={ ( v ) =>
							setAttributes( { charcoalColor: v } )
						}
						enableAlpha={ false }
						defaultValue="#263238"
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/hero-banner-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
