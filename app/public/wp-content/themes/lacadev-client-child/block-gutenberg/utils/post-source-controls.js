/**
 * post-source-controls.js — InspectorControls dùng chung cho mọi block hiển thị
 * NHIỀU bài viết/CPT theo 2 chế độ: 'auto' (query theo CPT + taxonomy + sắp xếp,
 * kể cả ngẫu nhiên) và 'manual' (tìm & chọn tay từng bài, hỗ trợ nhiều bài viết).
 *
 * Contract thuộc tính (đọc RULE_BLOCK_GUTENBERG.md trước khi đổi):
 *   mode           string  'auto' | 'manual' (+ block có thể thêm giá trị riêng, vd 'custom')
 *   postType       string  slug CPT, default 'post'
 *   taxonomy       string  slug taxonomy đang lọc, '' = không lọc
 *   selectedTerms  array   term ID đã chọn (mode auto)
 *   postsCount     number  số bài lấy ra (mode auto)
 *   orderBy        string  'date' | 'title' | 'menu_order' | 'rand'
 *   order          string  'ASC' | 'DESC' (bỏ qua khi orderBy === 'rand')
 *   selectedPosts  array   post ID đã chọn tay (mode manual)
 *
 * Block chỉ cần khai báo đúng các attribute trên trong block.json và dùng
 * <PostSourceControls attributes={attributes} setAttributes={setAttributes} />
 * trong InspectorControls — không cần viết lại logic fetch postType/taxonomy/terms.
 * Phần build WP_Query tương ứng ở PHP: xem utils/post-source-query.php (copy vào
 * từng block theo đúng lý do đã ghi trong RULE — cơ chế sync chỉ đóng gói 1 thư
 * mục block, không kéo theo file dùng chung ở utils/).
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import {
	PanelBody,
	SelectControl,
	RangeControl,
	CheckboxControl,
	RadioControl,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

const EXCLUDED_POST_TYPES = [
	'attachment',
	'wp_block',
	'wp_template',
	'wp_template_part',
	'wp_navigation',
	'wp_font_family',
	'wp_font_face',
];

export function usePostTypes() {
	return useSelect( ( select ) => {
		const types = select( 'core' ).getPostTypes( { per_page: -1 } );
		if ( ! types ) {
			return [];
		}
		return types
			.filter(
				( t ) => t.viewable && ! EXCLUDED_POST_TYPES.includes( t.slug )
			)
			.map( ( t ) => ( { label: t.name, value: t.slug } ) );
	}, [] );
}

export function usePostTaxonomies( postType ) {
	return useSelect(
		( select ) => {
			const types = select( 'core' ).getPostTypes( { per_page: -1 } );
			if ( ! types ) {
				return [];
			}
			const current = types.find( ( t ) => t.slug === postType );
			if ( ! current ) {
				return [];
			}
			return ( current.taxonomies || [] ).map( ( slug ) => {
				const tax = select( 'core' ).getTaxonomy( slug );
				return { label: tax ? tax.name : slug, value: slug };
			} );
		},
		[ postType ]
	);
}

export function useTaxonomyTerms( taxonomy ) {
	return useSelect(
		( select ) => {
			if ( ! taxonomy ) {
				return [];
			}
			return (
				select( 'core' ).getEntityRecords( 'taxonomy', taxonomy, {
					per_page: 50,
				} ) || []
			);
		},
		[ taxonomy ]
	);
}

export function useManualPostsList( mode, postType, search ) {
	return useSelect(
		( select ) => {
			if ( mode !== 'manual' ) {
				return [];
			}
			return (
				select( 'core' ).getEntityRecords( 'postType', postType, {
					per_page: 50,
					status: 'publish',
					search: search || undefined,
					_embed: true,
				} ) || []
			);
		},
		[ mode, postType, search ]
	);
}

export function PostSourceControls( {
	attributes,
	setAttributes,
	title,
	extraModeOptions = [],
} ) {
	const {
		mode,
		postType,
		taxonomy,
		selectedTerms,
		postsCount,
		orderBy,
		order,
		selectedPosts,
	} = attributes;

	const [ postSearch, setPostSearch ] = useState( '' );

	const postTypes = usePostTypes();
	const taxonomies = usePostTaxonomies( postType );
	const terms = useTaxonomyTerms( taxonomy );
	const manualPosts = useManualPostsList( mode, postType, postSearch );

	const toggleId = ( arr, id ) =>
		arr.includes( id ) ? arr.filter( ( x ) => x !== id ) : [ ...arr, id ];

	const taxonomyOptions = [
		{ label: __( '— Không lọc —', 'laca' ), value: '' },
		...taxonomies.map( ( tx ) => ( { label: tx.label, value: tx.value } ) ),
	];

	return (
		<PanelBody
			title={ title || __( 'Nguồn nội dung', 'laca' ) }
			initialOpen={ true }
		>
			<RadioControl
				label={ __( 'Chế độ', 'laca' ) }
				selected={ mode }
				options={ [
					...extraModeOptions,
					{
						label: __( 'Tự động (theo CPT/taxonomy)', 'laca' ),
						value: 'auto',
					},
					{
						label: __( 'Thủ công (tìm & chọn tay)', 'laca' ),
						value: 'manual',
					},
				] }
				onChange={ ( v ) => setAttributes( { mode: v } ) }
			/>

			{ ( mode === 'auto' || mode === 'manual' ) &&
				postTypes.length > 0 && (
					<SelectControl
						label={ __( 'Loại bài viết (Post Type)', 'laca' ) }
						value={ postType }
						options={ postTypes }
						onChange={ ( v ) =>
							setAttributes( {
								postType: v,
								taxonomy: '',
								selectedTerms: [],
								selectedPosts: [],
							} )
						}
					/>
				) }

			{ mode === 'auto' && (
				<>
					{ taxonomyOptions.length > 1 && (
						<SelectControl
							label={ __( 'Taxonomy', 'laca' ) }
							value={ taxonomy }
							options={ taxonomyOptions }
							onChange={ ( v ) =>
								setAttributes( {
									taxonomy: v,
									selectedTerms: [],
								} )
							}
						/>
					) }

					{ taxonomy && terms.length > 0 && (
						<>
							<p
								style={ {
									fontSize: '11px',
									fontWeight: 600,
									marginBottom: '6px',
								} }
							>
								{ __( 'Chọn danh mục (bỏ trống = tất cả)', 'laca' ) }
							</p>
							<div
								style={ {
									maxHeight: '200px',
									overflowY: 'auto',
									border: '1px solid #ddd',
									borderRadius: '4px',
									padding: '4px 8px',
								} }
							>
								{ terms.map( ( term ) => (
									<CheckboxControl
										key={ term.id }
										label={ `${ term.name } (${ term.count })` }
										checked={ selectedTerms.includes(
											term.id
										) }
										onChange={ () =>
											setAttributes( {
												selectedTerms: toggleId(
													selectedTerms,
													term.id
												),
											} )
										}
									/>
								) ) }
							</div>
						</>
					) }

					<RangeControl
						label={ __( 'Số bài viết hiển thị', 'laca' ) }
						value={ postsCount }
						min={ 1 }
						max={ 20 }
						onChange={ ( v ) => setAttributes( { postsCount: v } ) }
					/>

					<SelectControl
						label={ __( 'Sắp xếp theo', 'laca' ) }
						value={ orderBy }
						options={ [
							{ label: __( 'Ngày đăng', 'laca' ), value: 'date' },
							{ label: __( 'Tiêu đề', 'laca' ), value: 'title' },
							{
								label: __( 'Menu Order', 'laca' ),
								value: 'menu_order',
							},
							{ label: __( 'Ngẫu nhiên', 'laca' ), value: 'rand' },
						] }
						onChange={ ( v ) => setAttributes( { orderBy: v } ) }
					/>

					{ orderBy !== 'rand' && (
						<SelectControl
							label={ __( 'Thứ tự', 'laca' ) }
							value={ order }
							options={ [
								{
									label: __(
										'Giảm dần (mới/lớn nhất trước)',
										'laca'
									),
									value: 'DESC',
								},
								{
									label: __(
										'Tăng dần (cũ/nhỏ nhất trước)',
										'laca'
									),
									value: 'ASC',
								},
							] }
							onChange={ ( v ) => setAttributes( { order: v } ) }
						/>
					) }
				</>
			) }

			{ mode === 'manual' && (
				<>
					<p
						style={ {
							fontSize: '11px',
							color: '#666',
							margin: '4px 0 8px',
						} }
					>
						{ __( 'Đã chọn: ', 'laca' ) }
						<strong>{ selectedPosts.length }</strong>
					</p>
					<TextControl
						label={ __( 'Tìm bài viết', 'laca' ) }
						value={ postSearch }
						onChange={ setPostSearch }
						placeholder={ __( 'Nhập từ khóa…', 'laca' ) }
					/>
					<div
						style={ {
							maxHeight: '240px',
							overflowY: 'auto',
							border: '1px solid #ddd',
							borderRadius: '4px',
							padding: '4px 8px',
						} }
					>
						{ manualPosts.map( ( post ) => (
							<CheckboxControl
								key={ post.id }
								label={ post.title?.rendered || `#${ post.id }` }
								checked={ selectedPosts.includes( post.id ) }
								onChange={ () =>
									setAttributes( {
										selectedPosts: toggleId(
											selectedPosts,
											post.id
										),
									} )
								}
							/>
						) ) }
					</div>
				</>
			) }
		</PanelBody>
	);
}

export function ColumnsControl( { columns, onChange, min = 2, max = 6, label } ) {
	return (
		<RangeControl
			label={ label || __( 'Số cột hiển thị', 'laca' ) }
			value={ columns }
			min={ min }
			max={ max }
			onChange={ onChange }
		/>
	);
}
