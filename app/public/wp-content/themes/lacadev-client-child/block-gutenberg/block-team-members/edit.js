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
import { useInserterPreview, BlockPreviewMock } from '../utils/preview';
import previewImage from './preview.png';

const MAX_DOCTORS = 8;

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
									maxHeight: 120,
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
		doctors,
		specialtyColor,
		nameColor,
		cardBgColor,
		borderColor,
	} = attributes;
	const doctorsList = Array.isArray( doctors ) ? doctors : [];

	const blockProps = useBlockProps( {
		className: 'block-doctor-team block-doctor-team--editor-preview',
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Doctor Team', 'laca' ) }
				title={
					sectionTitle ||
					__( 'Đội ngũ bác sĩ chuyên môn cao', 'laca' )
				}
				columns={ 3 }
				image={ previewImage }
			/>
		);
	}

	const updateDoctor = ( index, key, value ) => {
		const updated = doctorsList.map( ( d, i ) =>
			i === index ? { ...d, [ key ]: value } : d
		);
		setAttributes( { doctors: updated } );
	};

	const addDoctor = () => {
		if ( doctorsList.length >= MAX_DOCTORS ) {
			return;
		}
		setAttributes( {
			doctors: [
				...doctorsList,
				{
					imageId: 0,
					imageUrl: '',
					name: 'TÊN BÁC SĨ',
					specialty: 'Chuyên khoa',
					bio: '',
				},
			],
		} );
	};

	const removeDoctor = ( index ) => {
		setAttributes( {
			doctors: doctorsList.filter( ( _, i ) => i !== index ),
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Tiêu đề section', 'laca' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Tiêu đề', 'laca' ) }
						value={ sectionTitle }
						onChange={ ( v ) =>
							setAttributes( { sectionTitle: v } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Danh sách bác sĩ (tối đa 8)', 'laca' ) }
					initialOpen={ false }
				>
					{ doctorsList.map( ( doctor, index ) => (
						<div
							key={ index }
							style={ {
								borderBottom: '1px solid #ddd',
								marginBottom: 16,
								paddingBottom: 16,
							} }
						>
							<p style={ { fontWeight: 600, marginBottom: 8 } }>
								{ __( 'Bác sĩ', 'laca' ) } { index + 1 }
							</p>

							<ImagePicker
								imageUrl={ doctor.imageUrl }
								imageId={ doctor.imageId }
								onSelect={ ( media ) => {
									updateDoctor( index, 'imageId', media.id );
									updateDoctor(
										index,
										'imageUrl',
										media.url
									);
								} }
							/>

							<PanelRow>
								<TextControl
									label={ __( 'Tên', 'laca' ) }
									value={ doctor.name }
									onChange={ ( v ) =>
										updateDoctor( index, 'name', v )
									}
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={ __( 'Chuyên khoa', 'laca' ) }
									value={ doctor.specialty }
									onChange={ ( v ) =>
										updateDoctor( index, 'specialty', v )
									}
								/>
							</PanelRow>
							<TextareaControl
								label={ __( 'Mô tả ngắn', 'laca' ) }
								value={ doctor.bio }
								onChange={ ( v ) =>
									updateDoctor( index, 'bio', v )
								}
								rows={ 3 }
							/>
							<Button
								isDestructive
								variant="secondary"
								onClick={ () => removeDoctor( index ) }
							>
								{ __( 'Xóa', 'laca' ) }
							</Button>
						</div>
					) ) }

					{ doctorsList.length < MAX_DOCTORS && (
						<Button variant="primary" onClick={ addDoctor }>
							{ __( '+ Thêm bác sĩ', 'laca' ) }
						</Button>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Giao diện', 'laca' ) }
					initialOpen={ false }
				>
					<p style={ { fontSize: '0.8rem', fontWeight: 600, marginBottom: '0.5rem' } }>
						{ __( 'Màu tên bác sĩ', 'laca' ) }
					</p>
					<ColorPicker
						color={ nameColor }
						onChange={ ( v ) => setAttributes( { nameColor: v } ) }
						enableAlpha={ false }
						defaultValue="#263238"
					/>

					<p style={ { fontSize: '0.8rem', fontWeight: 600, margin: '1rem 0 0.5rem' } }>
						{ __( 'Màu chuyên khoa', 'laca' ) }
					</p>
					<ColorPicker
						color={ specialtyColor }
						onChange={ ( v ) =>
							setAttributes( { specialtyColor: v } )
						}
						enableAlpha={ false }
						defaultValue="#0d631b"
					/>

					<p style={ { fontSize: '0.8rem', fontWeight: 600, margin: '1rem 0 0.5rem' } }>
						{ __( 'Màu nền thẻ', 'laca' ) }
					</p>
					<ColorPicker
						color={ cardBgColor }
						onChange={ ( v ) =>
							setAttributes( { cardBgColor: v } )
						}
						enableAlpha={ false }
						defaultValue="#ffffff"
					/>

					<p style={ { fontSize: '0.8rem', fontWeight: 600, margin: '1rem 0 0.5rem' } }>
						{ __( 'Màu viền thẻ', 'laca' ) }
					</p>
					<ColorPicker
						color={ borderColor }
						onChange={ ( v ) =>
							setAttributes( { borderColor: v } )
						}
						enableAlpha={ false }
						defaultValue="#bfcaba"
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="block-doctor-team__editor-inner">
					{ sectionTitle ? (
						<div className="block-doctor-team__editor-header">
							<h2 className="block-doctor-team__editor-heading">
								{ sectionTitle }
							</h2>
						</div>
					) : null }

					{ doctorsList.length > 0 ? (
						<div className="block-doctor-team__editor-grid">
							{ doctorsList.map( ( doctor, index ) => (
								<div
									key={ index }
									className="block-doctor-team__editor-card"
									style={ {
										background: cardBgColor || '#ffffff',
										borderColor: borderColor || '#bfcaba',
									} }
								>
									<div className="block-doctor-team__editor-thumb">
										{ doctor.imageUrl ? (
											<img
												src={ doctor.imageUrl }
												alt={ doctor.name || '' }
											/>
										) : (
											<div className="block-doctor-team__editor-thumb-placeholder">
												{ __( 'Chưa có ảnh', 'laca' ) }
											</div>
										) }
									</div>
									<div className="block-doctor-team__editor-meta">
										{ doctor.name ? (
											<div
												className="block-doctor-team__editor-name"
												style={ {
													color:
														nameColor || '#263238',
												} }
											>
												{ doctor.name }
											</div>
										) : null }
										{ doctor.specialty ? (
											<div
												className="block-doctor-team__editor-specialty"
												style={ {
													color:
														specialtyColor ||
														'#0d631b',
												} }
											>
												{ doctor.specialty }
											</div>
										) : null }
										{ doctor.bio ? (
											<div className="block-doctor-team__editor-bio">
												{ doctor.bio }
											</div>
										) : null }
									</div>
								</div>
							) ) }
						</div>
					) : (
						<p className="block-doctor-team__editor-empty">
							{ __(
								'Thêm bác sĩ trong sidebar (tối đa 8).',
								'laca'
							) }
						</p>
					) }
				</div>
			</div>
		</>
	);
}
