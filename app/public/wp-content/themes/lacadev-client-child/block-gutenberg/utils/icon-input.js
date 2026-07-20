/**
 * icon-input.js — control nhập icon dùng chung cho mọi block có danh sách
 * mục kèm icon (stats, feature list…). Thay thế hoàn toàn cách nhập "tên
 * icon" tra theo 1 bộ SVG tự vẽ giới hạn (không khớp icon thật của Material
 * Symbols) bằng 2 lựa chọn: dán thẳng SVG code, hoặc tải ảnh PNG/SVG lên.
 *
 * Contract dữ liệu (đọc RULE_BLOCK_GUTENBERG.md trước khi đổi):
 *   icon = { type: 'svg' | 'image', svg: string, imageId: number, imageUrl: string }
 *
 * Bản PHP tương ứng: utils/icon-render.php (copy vào từng block cần dùng,
 * cùng lý do sync-safety như mọi PHP helper dùng chung khác).
 */
import { __ } from '@wordpress/i18n';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { BaseControl, Button, ButtonGroup, TextareaControl } from '@wordpress/components';

export function IconInput( { icon, onChange, label } ) {
	const type = icon?.type || 'svg';

	const setType = ( newType ) => onChange( { ...icon, type: newType } );

	return (
		<BaseControl label={ label || __( 'Icon', 'laca' ) }>
			<ButtonGroup style={ { marginBottom: 8, display: 'flex', gap: 4 } }>
				<Button
					variant={ type === 'svg' ? 'primary' : 'secondary' }
					onClick={ () => setType( 'svg' ) }
				>
					{ __( 'Dán SVG code', 'laca' ) }
				</Button>
				<Button
					variant={ type === 'image' ? 'primary' : 'secondary' }
					onClick={ () => setType( 'image' ) }
				>
					{ __( 'Tải ảnh', 'laca' ) }
				</Button>
			</ButtonGroup>

			{ type === 'svg' && (
				<>
					<TextareaControl
						help={ __(
							'Dán nguyên code <svg>...</svg> — vd lấy từ fonts.google.com/icons, chọn icon rồi bấm tab "SVG" (không phải phần font-embed). Hỗ trợ SVG đơn giản (path/circle/rect/line/polyline/polygon), không hỗ trợ gradient/mask/filter.',
							'laca'
						) }
						value={ icon?.svg || '' }
						onChange={ ( v ) =>
							onChange( { ...icon, type: 'svg', svg: v } )
						}
						rows={ 4 }
					/>
					{ icon?.svg && (
						<div
							style={ {
								width: 32,
								height: 32,
								display: 'inline-flex',
								alignItems: 'center',
								justifyContent: 'center',
								border: '1px solid #ddd',
								borderRadius: 4,
								padding: 4,
							} }
							dangerouslySetInnerHTML={ { __html: icon.svg } }
						/>
					) }
				</>
			) }

			{ type === 'image' && (
				<MediaUploadCheck>
					<MediaUpload
						onSelect={ ( media ) =>
							onChange( {
								...icon,
								type: 'image',
								imageId: media.id,
								imageUrl: media.url,
							} )
						}
						allowedTypes={ [ 'image' ] }
						value={ icon?.imageId }
						render={ ( { open } ) => (
							<div>
								{ icon?.imageUrl && (
									<img
										src={ icon.imageUrl }
										alt=""
										style={ {
											width: 40,
											height: 40,
											objectFit: 'contain',
											marginBottom: 4,
											display: 'block',
										} }
									/>
								) }
								<Button variant="secondary" onClick={ open }>
									{ icon?.imageUrl
										? __( 'Đổi ảnh', 'laca' )
										: __( 'Chọn ảnh', 'laca' ) }
								</Button>
							</div>
						) }
					/>
				</MediaUploadCheck>
			) }
		</BaseControl>
	);
}
