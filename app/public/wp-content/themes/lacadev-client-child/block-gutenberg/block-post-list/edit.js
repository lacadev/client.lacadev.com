import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import {
	BlockBasePanels,
	BlockPreviewMock,
	BlockSectionHeaderPreview,
	getSpacingVars,
	hexToRgba,
	normalizeBlockScaffoldAttributes,
	useInserterPreview,
} from '../utils';

const previewPosts = [ 1, 2, 3, 4 ];

export default function Edit( { attributes, setAttributes } ) {
	const isPreview = useInserterPreview( attributes );
	const normalizedAttributes = normalizeBlockScaffoldAttributes( attributes, {
		bgColor: '#ffffff',
		bgOpacity: 100,
	} );
	const {
		heading,
		subheading,
		headingTag,
		headingAlign,
		subheadingAlign,
		headingColor,
		subheadingColor,
		bgColor,
		bgOpacity,
		spacing,
		postType,
		postsPerPage,
		layout,
		showExcerpt,
		showDate,
		buttonLabel,
	} = normalizedAttributes;

	const blockProps = useBlockProps( {
		className: `laca-post-list-block is-layout-${ layout }`,
		style: {
			background: hexToRgba( bgColor, bgOpacity ),
			...getSpacingVars( spacing, '--laca-post-list' ),
		},
	} );

	if ( isPreview ) {
		return (
			<BlockPreviewMock
				kicker={ __( 'Post List', 'lacadev' ) }
				title={ __( 'Danh sach bai viet dong', 'lacadev' ) }
				columns={ 3 }
			/>
		);
	}

	return (
		<>
			<InspectorControls>
				<BlockBasePanels
					attributes={ normalizedAttributes }
					setAttributes={ setAttributes }
					textdomain="lacadev"
					configPanelTitle="Cấu hình danh sách bài viết"
					commonContentPanelTitle="Nội dung chung"
					commonStylePanelTitle="Style chung"
					titleLabel="Tiêu đề danh sách"
					subtitleLabel="Phụ đề danh sách"
					configChildren={
						<>
							<TextControl
								label={ __( 'Post type', 'lacadev' ) }
								value={ postType }
								onChange={ ( value ) => setAttributes( { postType: value } ) }
								help={ __( 'Nhập post, page hoặc custom post type slug.', 'lacadev' ) }
							/>
							<RangeControl
								label={ __( 'Số lượng bài', 'lacadev' ) }
								value={ postsPerPage }
								min={ 1 }
								max={ 8 }
								onChange={ ( value ) => setAttributes( { postsPerPage: value } ) }
							/>
							<SelectControl
								label={ __( 'Layout', 'lacadev' ) }
								value={ layout }
								options={ [
									{ label: __( 'Grid', 'lacadev' ), value: 'grid' },
									{ label: __( 'List', 'lacadev' ), value: 'list' },
								] }
								onChange={ ( value ) => setAttributes( { layout: value } ) }
							/>
							<ToggleControl
								label={ __( 'Hiển thị excerpt', 'lacadev' ) }
								checked={ showExcerpt }
								onChange={ ( value ) => setAttributes( { showExcerpt: value } ) }
							/>
							<ToggleControl
								label={ __( 'Hiển thị ngày đăng', 'lacadev' ) }
								checked={ showDate }
								onChange={ ( value ) => setAttributes( { showDate: value } ) }
							/>
							<TextControl
								label={ __( 'Nhãn nút đọc tiếp', 'lacadev' ) }
								value={ buttonLabel }
								onChange={ ( value ) => setAttributes( { buttonLabel: value } ) }
							/>
						</>
					}
				/>
			</InspectorControls>

			<div { ...blockProps }>
				<BlockSectionHeaderPreview
					wrapperClassName="laca-block-section-header laca-post-list-block__header"
					heading={ heading }
					subheading={ subheading }
					headingTag={ headingTag }
					headingAlign={ headingAlign }
					subheadingAlign={ subheadingAlign }
					headingColor={ headingColor }
					subheadingColor={ subheadingColor }
				/>
				<div className={ `laca-post-list-block__grid is-layout-${ layout }` }>
					{ previewPosts.slice( 0, postsPerPage ).map( ( item ) => (
						<article key={ `post-preview-${ item }` } className="laca-post-list-block__card">
							<span className="laca-post-list-block__type">{ postType }</span>
							<h3>Bài viết mẫu { item }</h3>
							{ showDate ? <time>16/05/2026</time> : null }
							{ showExcerpt ? (
								<p>Đây là preview tĩnh trong editor để mô phỏng cách danh sách bài viết sẽ hiển thị ngoài frontend.</p>
							) : null }
							<span className="laca-post-list-block__link">{ buttonLabel || 'Đọc tiếp' }</span>
						</article>
					) ) }
				</div>
			</div>
		</>
	);
}