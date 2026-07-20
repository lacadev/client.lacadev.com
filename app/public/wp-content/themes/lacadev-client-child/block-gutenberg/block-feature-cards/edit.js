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
	Button,
	ColorPicker,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useInserterPreview, BlockPreviewMock } from '../utils/preview';
import {
	PostSourceControls,
	ColumnsControl,
} from '../utils/post-source-controls';
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
		sectionSubtitle,
		services,
		mode,
		ctaText,
		columns,
		bgColor,
		titleColor,
		accentColor,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wp-block-lacadev-feature-cards-block',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Feature Cards', 'laca' ) }
				title={
					attributes.sectionTitle ||
					__( 'Dịch vụ chuyên sâu', 'laca' )
				}
				columns={ columns || 4 }
				image={ previewImage }
			/>
		);
	}

	const updateService = ( index, key, value ) => {
		const updated = services.map( ( item, i ) =>
			i === index ? { ...item, [ key ]: value } : item
		);
		setAttributes( { services: updated } );
	};

	const addService = () => {
		setAttributes( {
			services: [
				...services,
				{
					imageId: 0,
					imageUrl: '',
					title: __( 'Dịch vụ mới', 'laca' ),
					description: '',
					link: '',
					ctaText: __( 'Xem chi tiết', 'laca' ),
				},
			],
		} );
	};

	const removeService = ( index ) => {
		setAttributes( {
			services: services.filter( ( _, i ) => i !== index ),
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
					<TextareaControl
						label={ __( 'Mô tả Section', 'laca' ) }
						value={ sectionSubtitle || '' }
						onChange={ ( v ) =>
							setAttributes( { sectionSubtitle: v } )
						}
					/>
					<ColumnsControl
						columns={ columns }
						onChange={ ( v ) => setAttributes( { columns: v } ) }
					/>
				</PanelBody>

				<PostSourceControls
					attributes={ attributes }
					setAttributes={ setAttributes }
					title={ __( 'Nguồn nội dung', 'laca' ) }
					extraModeOptions={ [
						{
							label: __(
								'Nhập tay (danh sách cố định)',
								'laca'
							),
							value: 'custom',
						},
					] }
				/>

				{ ( mode === 'auto' || mode === 'manual' ) && (
					<PanelBody
						title={ __( 'Nội dung thẻ', 'laca' ) }
						initialOpen={ false }
					>
						<TextControl
							label={ __( 'CTA Text (áp dụng cho mọi thẻ)', 'laca' ) }
							value={ ctaText || '' }
							onChange={ ( v ) =>
								setAttributes( { ctaText: v } )
							}
						/>
					</PanelBody>
				) }

				{ mode === 'custom' && (
					<>
						{ services.map( ( service, index ) => (
							<PanelBody
								key={ index }
								title={ `${ __( 'Dịch vụ', 'laca' ) } ${
									index + 1
								}: ${ service.title || '' }` }
								initialOpen={ index === 0 }
							>
								<ImagePicker
									label={ __( 'Ảnh', 'laca' ) }
									imageUrl={ service.imageUrl }
									imageId={ service.imageId }
									onSelect={ ( media ) => {
										updateService(
											index,
											'imageId',
											media.id
										);
										updateService(
											index,
											'imageUrl',
											media.url
										);
									} }
								/>
								<TextControl
									label={ __( 'Tiêu đề', 'laca' ) }
									value={ service.title }
									onChange={ ( v ) =>
										updateService( index, 'title', v )
									}
								/>
								<TextareaControl
									label={ __( 'Mô tả', 'laca' ) }
									value={ service.description }
									onChange={ ( v ) =>
										updateService(
											index,
											'description',
											v
										)
									}
								/>
								<TextControl
									label={ __( 'Link (tùy chọn)', 'laca' ) }
									value={ service.link || '' }
									onChange={ ( v ) =>
										updateService( index, 'link', v )
									}
								/>
								<TextControl
									label={ __( 'CTA Text', 'laca' ) }
									value={ service.ctaText || '' }
									onChange={ ( v ) =>
										updateService( index, 'ctaText', v )
									}
								/>

								<Button
									isDestructive
									variant="secondary"
									onClick={ () => removeService( index ) }
									style={ { marginTop: 8 } }
								>
									{ __( 'Xóa', 'laca' ) }
								</Button>
							</PanelBody>
						) ) }

						<div style={ { padding: '8px 16px 16px' } }>
							<Button variant="primary" onClick={ addService }>
								{ __( '+ Thêm dịch vụ', 'laca' ) }
							</Button>
						</div>
					</>
				) }

				<PanelBody title={ __( 'Giao diện', 'laca' ) } initialOpen={ false }>
					<p style={ { marginBottom: 4 } }>
						{ __( 'Màu nền section', 'laca' ) }
					</p>
					<ColorPicker
						color={ bgColor }
						onChange={ ( v ) => setAttributes( { bgColor: v } ) }
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
						{ __( 'Màu nhấn (CTA / hover)', 'laca' ) }
					</p>
					<ColorPicker
						color={ accentColor }
						onChange={ ( v ) =>
							setAttributes( { accentColor: v } )
						}
						enableAlpha={ false }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="lacadev/feature-cards-block"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
