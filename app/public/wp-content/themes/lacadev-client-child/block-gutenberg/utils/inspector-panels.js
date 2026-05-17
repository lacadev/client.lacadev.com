import { __ } from '@wordpress/i18n';
import {
	Button,
	ButtonGroup,
	PanelBody,
	SelectControl,
	TextControl,
	ColorPicker,
	RangeControl,
} from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import {
	BLOCK_SPACING_DEVICES,
	BLOCK_SPACING_SIDES,
	createResponsiveSpacing,
	normalizeResponsiveSpacing,
} from './block-defaults';
import { SectionHeaderStyleFields } from './section-header';

const SPACING_UNITS = [ 'px', 'rem', 'em', '%', 'vw', 'vh' ];

function parseSpacingInput( value ) {
	const normalized = `${ value ?? '' }`.trim();
	if ( ! normalized ) {
		return { amount: '', unit: 'px' };
	}

	const withUnit = normalized.match(
		/^(-?\d+(?:\.\d+)?)(px|rem|em|%|vw|vh)$/
	);
	if ( withUnit ) {
		return { amount: withUnit[ 1 ], unit: withUnit[ 2 ] };
	}

	if ( /^-?\d+(\.\d+)?$/.test( normalized ) ) {
		return { amount: normalized, unit: 'px' };
	}

	// Fallback for unexpected legacy values; keep editable amount and default unit.
	return { amount: normalized, unit: 'px' };
}

function ResponsiveSpacingControls( { textdomain, spacing, onChangeSpacing } ) {
	const [ activeDevice, setActiveDevice ] = useState( 'desktop' );

	const resolvedSpacing = useMemo(
		() => normalizeResponsiveSpacing( spacing ),
		[ spacing ]
	);
	const deviceSpacing = resolvedSpacing[ activeDevice ];

	const updateSpacing = ( type, side, { amount, unit } ) => {
		const nextSpacing = normalizeResponsiveSpacing( resolvedSpacing );
		const normalizedAmount = `${ amount ?? '' }`.trim();
		nextSpacing[ activeDevice ][ type ][ side ] = normalizedAmount
			? `${ normalizedAmount }${ unit || 'px' }`
			: '';
		onChangeSpacing( nextSpacing );
	};

	return (
		<>
			<p style={ { marginBottom: '8px', fontWeight: 600 } }>
				{ __( 'Responsive', textdomain ) }
			</p>
			<ButtonGroup style={ { marginBottom: '12px' } }>
				{ BLOCK_SPACING_DEVICES.map( ( device ) => (
					<Button
						key={ device }
						isPrimary={ activeDevice === device }
						isSecondary={ activeDevice !== device }
						onClick={ () => setActiveDevice( device ) }
					>
						{ __(
							device.charAt( 0 ).toUpperCase() +
								device.slice( 1 ),
							textdomain
						) }
					</Button>
				) ) }
			</ButtonGroup>

			<p style={ { marginBottom: '6px', fontWeight: 600 } }>
				{ __( 'Margin', textdomain ) }
			</p>
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(4, minmax(0, 1fr))',
					gap: '8px',
					marginBottom: '12px',
				} }
			>
				{ BLOCK_SPACING_SIDES.map( ( side ) => {
					const parsed = parseSpacingInput(
						deviceSpacing.margin[ side ]
					);
					return (
						<div key={ `margin-${ side }` }>
							<TextControl
								label={ __( side, textdomain ) }
								value={ parsed.amount }
								placeholder=""
								onChange={ ( value ) =>
									updateSpacing( 'margin', side, {
										amount: value,
										unit: parsed.unit,
									} )
								}
							/>
							<SelectControl
								label=""
								value={ parsed.unit }
								options={ SPACING_UNITS.map( ( unit ) => ( {
									label: unit,
									value: unit,
								} ) ) }
								onChange={ ( value ) =>
									updateSpacing( 'margin', side, {
										amount: parsed.amount,
										unit: value,
									} )
								}
							/>
						</div>
					);
				} ) }
			</div>

			<p style={ { marginBottom: '6px', fontWeight: 600 } }>
				{ __( 'Padding', textdomain ) }
			</p>
			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: 'repeat(4, minmax(0, 1fr))',
					gap: '8px',
				} }
			>
				{ BLOCK_SPACING_SIDES.map( ( side ) => {
					const parsed = parseSpacingInput(
						deviceSpacing.padding[ side ]
					);
					return (
						<div key={ `padding-${ side }` }>
							<TextControl
								label={ __( side, textdomain ) }
								value={ parsed.amount }
								placeholder=""
								onChange={ ( value ) =>
									updateSpacing( 'padding', side, {
										amount: value,
										unit: parsed.unit,
									} )
								}
							/>
							<SelectControl
								label=""
								value={ parsed.unit }
								options={ SPACING_UNITS.map( ( unit ) => ( {
									label: unit,
									value: unit,
								} ) ) }
								onChange={ ( value ) =>
									updateSpacing( 'padding', side, {
										amount: parsed.amount,
										unit: value,
									} )
								}
							/>
						</div>
					);
				} ) }
			</div>
		</>
	);
}

function PanelSectionLabel( { children } ) {
	return (
		<p
			style={ {
				marginTop: '0',
				marginBottom: '8px',
				fontSize: '11px',
				fontWeight: 600,
				textTransform: 'uppercase',
				color: '#50575e',
				letterSpacing: '0.04em',
			} }
		>
			{ children }
		</p>
	);
}

function PanelSectionDivider() {
	return (
		<div style={ { borderTop: '1px solid #dcdcde', margin: '16px 0' } } />
	);
}

function TitleField( {
	value,
	onChange,
	textdomain = 'laca',
	label = 'Tiêu đề',
	placeholder = '',
} ) {
	return (
		<TextControl
			label={ label }
			value={ value || '' }
			onChange={ onChange }
			placeholder={ placeholder }
		/>
	);
}

function SubtitleField( {
	value,
	onChange,
	textdomain = 'laca',
	label = 'Phụ đề',
	placeholder = '',
} ) {
	return (
		<TextControl
			label={ label }
			value={ value || '' }
			onChange={ onChange }
			placeholder={ placeholder }
		/>
	);
}

/**
 * Shared inspector panel for block-specific settings.
 *
 * @param {Object} props Component props.
 * @param {string} props.title Panel title.
 * @param {string} props.textdomain Translation domain.
 * @param {boolean} props.initialOpen Initial open state.
 * @param {*} props.children Custom block controls.
 * @return {JSX.Element} Panel body.
 */
export function BlockConfigPanel( {
	title = 'Cấu hình block',
	textdomain = 'laca',
	initialOpen = true,
	children,
} ) {
	return (
		<PanelBody title={ title } initialOpen={ initialOpen }>
			{ children }
		</PanelBody>
	);
}

/**
 * Nhóm field dùng chung cho giao diện + spacing.
 * Tách riêng để tái sử dụng trong nhiều panel mà không lặp code.
 *
 * @param {Object} props Component props.
 * @param {string} props.textdomain Textdomain để dịch.
 * @param {string} props.bgColor Màu nền hiện tại.
 * @param {number} props.bgOpacity Độ mờ nền hiện tại.
 * @param {Object} props.spacing Cấu hình spacing responsive.
 * @param {Function} props.setAttributes Hàm setAttributes của block.
 * @param {string} props.attributeKey Tên attribute spacing.
 * @return {JSX.Element} Nhóm field UI.
 */
function AppearanceAndSpacingFields( {
	textdomain,
	bgColor,
	bgOpacity,
	spacing,
	setAttributes,
	attributeKey = 'spacing',
} ) {
	return (
		<>
			<p
				style={ {
					fontSize: '0.8rem',
					fontWeight: 600,
					marginBottom: '0.5rem',
				} }
			>
				{ __( 'Background color', textdomain ) }
			</p>
			<ColorPicker
				color={ bgColor || 'transparent' }
				onChange={ ( value ) => setAttributes( { bgColor: value } ) }
				enableAlpha={ false }
				defaultValue="transparent"
			/>

			<RangeControl
				label={ __( 'Opacity (0 = transparent)', textdomain ) }
				value={ typeof bgOpacity === 'number' ? bgOpacity : 100 }
				min={ 0 }
				max={ 100 }
				step={ 5 }
				onChange={ ( value ) => setAttributes( { bgOpacity: value } ) }
			/>

			<div style={ { marginTop: '12px' } }>
				<ResponsiveSpacingControls
					textdomain={ textdomain }
					spacing={ spacing }
					onChangeSpacing={ ( nextSpacing ) =>
						setAttributes( { [ attributeKey ]: nextSpacing } )
					}
				/>
			</div>
		</>
	);
}

/**
 * Shared title panel.
 *
 * @param {Object} props Component props.
 * @param {string} props.value Current title value.
 * @param {Function} props.onChange Change handler.
 * @param {string} props.textdomain Translation domain.
 * @param {string} props.label Input label.
 * @param {string} props.placeholder Input placeholder.
 * @return {JSX.Element} Panel body.
 */
export function TitlePanel( {
	value,
	onChange,
	textdomain = 'laca',
	label = 'Tiêu đề',
	placeholder = '',
} ) {
	return (
		<PanelBody title={ __( 'Tiêu đề', textdomain ) } initialOpen={ false }>
			<TitleField
				value={ value }
				onChange={ onChange }
				textdomain={ textdomain }
				label={ label }
				placeholder={ placeholder }
			/>
		</PanelBody>
	);
}

/**
 * Shared subtitle panel.
 *
 * @param {Object} props Component props.
 * @param {string} props.value Current subtitle value.
 * @param {Function} props.onChange Change handler.
 * @param {string} props.textdomain Translation domain.
 * @param {string} props.label Input label.
 * @param {string} props.placeholder Input placeholder.
 * @return {JSX.Element} Panel body.
 */
export function SubtitlePanel( {
	value,
	onChange,
	textdomain = 'laca',
	label = 'Phụ đề',
	placeholder = '',
} ) {
	return (
		<PanelBody title={ __( 'Phụ đề', textdomain ) } initialOpen={ false }>
			<SubtitleField
				value={ value }
				onChange={ onChange }
				textdomain={ textdomain }
				label={ label }
				placeholder={ placeholder }
			/>
		</PanelBody>
	);
}

/**
 * Shared appearance panel for background color and opacity.
 *
 * @param {Object} props Component props.
 * @param {string} props.textdomain Translation domain.
 * @param {string} props.bgColor Current background color.
 * @param {number} props.bgOpacity Current background opacity.
 * @param {Function} props.setAttributes Gutenberg setAttributes function.
 * @return {JSX.Element} Panel body.
 */
export function AppearancePanel( {
	textdomain = 'laca',
	panelTitle = 'Hiển thị chung',
	bgColor = 'transparent',
	bgOpacity = 100,
	spacing,
	attributeKey = 'spacing',
	setAttributes,
} ) {
	return (
		<PanelBody title={ panelTitle } initialOpen={ true }>
			<AppearanceAndSpacingFields
				textdomain={ textdomain }
				bgColor={ bgColor }
				bgOpacity={ bgOpacity }
				spacing={ spacing }
				setAttributes={ setAttributes }
				attributeKey={ attributeKey }
			/>
		</PanelBody>
	);
}

export function CommonHeaderPanel( {
	textdomain = 'laca',
	panelTitle = 'Tiêu đề & phụ đề',
	showTitle = true,
	showSubtitle = true,
	heading = '',
	subheading = '',
	headingTag = 'h2',
	headingAlign = 'left',
	subheadingAlign = 'left',
	headingColor = '#111111',
	subheadingColor = '#6b7280',
	setAttributes,
	titleLabel = 'Tiêu đề section',
	subtitleLabel = 'Phụ đề section',
	titlePlaceholder = '',
	subtitlePlaceholder = '',
	initialOpen = false,
	showHeaderStyleFields = true,
} ) {
	if ( ! showTitle && ! showSubtitle ) {
		return null;
	}

	return (
		<PanelBody title={ panelTitle } initialOpen={ initialOpen }>
			<PanelSectionLabel>Nhập nội dung</PanelSectionLabel>

			{ showTitle ? (
				<TitleField
					textdomain={ textdomain }
					value={ heading }
					onChange={ ( value ) => setAttributes( { heading: value } ) }
					label={ titleLabel }
					placeholder={ titlePlaceholder }
				/>
			) : null }

			{ showTitle && showSubtitle ? <PanelSectionDivider /> : null }

			{ showSubtitle ? (
				<SubtitleField
					textdomain={ textdomain }
					value={ subheading }
					onChange={ ( value ) => setAttributes( { subheading: value } ) }
					label={ subtitleLabel }
					placeholder={ subtitlePlaceholder }
				/>
			) : null }

			{ ( showTitle || showSubtitle ) && showHeaderStyleFields ? (
				<PanelSectionDivider />
			) : null }

			{ showHeaderStyleFields ? (
				<>
					<PanelSectionLabel>Style tiêu đề</PanelSectionLabel>
					<SectionHeaderStyleFields
						headingTag={ headingTag }
						headingAlign={ headingAlign }
						subheadingAlign={ subheadingAlign }
						headingColor={ headingColor }
						subheadingColor={ subheadingColor }
						textdomain={ textdomain }
						setAttributes={ setAttributes }
					/>
				</>
			) : null }
		</PanelBody>
	);
}

export function CommonStylePanel( {
	textdomain = 'laca',
	panelTitle = 'Hiển thị chung',
	initialOpen = false,
	showAppearanceFields = true,
	bgColor = 'transparent',
	bgOpacity = 100,
	spacing,
	spacingAttributeKey = 'spacing',
	setAttributes,
} ) {
	if ( ! showAppearanceFields ) {
		return null;
	}

	return (
		<PanelBody title={ panelTitle } initialOpen={ initialOpen }>
			{ showAppearanceFields ? (
				<>
					<PanelSectionLabel>Nền và khoảng cách</PanelSectionLabel>
					<AppearanceAndSpacingFields
						textdomain={ textdomain }
						bgColor={ bgColor }
						bgOpacity={ bgOpacity }
						spacing={ spacing }
						setAttributes={ setAttributes }
						attributeKey={ spacingAttributeKey }
					/>
				</>
			) : null }
		</PanelBody>
	);
}

/**
 * Bộ panel chuẩn dùng chung cho tất cả block hiện tại và tương lai.
 * - Panel đầu là cấu hình chức năng riêng của block.
 * - Tiêu đề/phụ đề và style của chúng nằm chung một panel.
 * - Background + spacing nằm ở panel hiển thị chung riêng.
 *
 * @param {Object} props Component props.
 * @param {Object} props.attributes Toàn bộ attributes của block.
 * @param {Function} props.setAttributes Hàm setAttributes.
 * @param {string} props.textdomain Textdomain để dịch.
 * @param {boolean} props.showTitle Bật/tắt panel tiêu đề.
 * @param {boolean} props.showSubtitle Bật/tắt panel phụ đề.
 * @param {string} props.titleLabel Nhãn input tiêu đề.
 * @param {string} props.subtitleLabel Nhãn input phụ đề.
 * @param {string} props.titlePlaceholder Placeholder tiêu đề.
 * @param {string} props.subtitlePlaceholder Placeholder phụ đề.
 * @param {*} props.configChildren Controls riêng của block đặt trong panel cấu hình.
 * @param {string} props.spacingAttributeKey Tên attribute spacing.
 * @param {boolean} props.showHeaderStylePanel Hiển thị phần style tiêu đề trong panel chung.
 * @param {string} props.configPanelTitle Tiêu đề panel cấu hình riêng của block.
 * @param {string} props.commonHeaderPanelTitle Tiêu đề panel tiêu đề/phụ đề chung.
 * @param {string} props.appearancePanelTitle Tiêu đề panel hiển thị chung.
 * @param {string} props.commonContentPanelTitle Alias cũ, giữ để tương thích ngược.
 * @param {string} props.commonStylePanelTitle Alias cũ, giữ để tương thích ngược.
 * @return {JSX.Element} Cụm panel Inspector chuẩn hoá.
 */
export function BlockBasePanels( {
	attributes,
	setAttributes,
	textdomain = 'laca',
	showTitle = true,
	showSubtitle = true,
	titleLabel = 'Tiêu đề section',
	subtitleLabel = 'Phụ đề section',
	titlePlaceholder = '',
	subtitlePlaceholder = '',
	configChildren = null,
	spacingAttributeKey = 'spacing',
	showHeaderStylePanel = true,
	configPanelTitle = 'Cấu hình chức năng',
	commonHeaderPanelTitle,
	appearancePanelTitle,
	commonContentPanelTitle,
	commonStylePanelTitle,
} ) {
	const {
		heading = '',
		subheading = '',
		headingTag = 'h2',
		headingAlign = 'left',
		subheadingAlign = 'left',
		headingColor = '#111111',
		subheadingColor = '#6b7280',
		bgColor = 'transparent',
		bgOpacity = 100,
		spacing = createResponsiveSpacing(),
	} = attributes || {};

	const resolvedHeaderPanelTitle =
		commonHeaderPanelTitle ||
		( commonContentPanelTitle === 'Nội dung chung'
			? 'Tiêu đề & phụ đề'
			: commonContentPanelTitle ) ||
		'Tiêu đề & phụ đề';

	const resolvedAppearancePanelTitle =
		appearancePanelTitle ||
		( commonStylePanelTitle === 'Style chung'
			? 'Hiển thị chung'
			: commonStylePanelTitle ) ||
		'Hiển thị chung';

	return (
		<>
			{ configChildren ? (
				<BlockConfigPanel title={ configPanelTitle } textdomain={ textdomain }>
					{ configChildren }
				</BlockConfigPanel>
			) : null }

			<CommonHeaderPanel
				textdomain={ textdomain }
				panelTitle={ resolvedHeaderPanelTitle }
				showTitle={ showTitle }
				showSubtitle={ showSubtitle }
				heading={ heading }
				subheading={ subheading }
				headingTag={ headingTag }
				headingAlign={ headingAlign }
				subheadingAlign={ subheadingAlign }
				headingColor={ headingColor }
				subheadingColor={ subheadingColor }
				setAttributes={ setAttributes }
				titleLabel={ titleLabel }
				subtitleLabel={ subtitleLabel }
				titlePlaceholder={ titlePlaceholder }
				subtitlePlaceholder={ subtitlePlaceholder }
				showHeaderStyleFields={ showHeaderStylePanel && ( showTitle || showSubtitle ) }
			/>

			<CommonStylePanel
				textdomain={ textdomain }
				panelTitle={ resolvedAppearancePanelTitle }
				showAppearanceFields={ true }
				bgColor={ bgColor }
				bgOpacity={ bgOpacity }
				spacing={ spacing }
				spacingAttributeKey={ spacingAttributeKey }
				setAttributes={ setAttributes }
			/>
		</>
	);
}

/**
 * Shared spacing panel for section spacing.
 *
 * @param {Object} props Component props.
 * @param {string} props.textdomain Translation domain.
 * @param {number} props.marginTop Margin top in px.
 * @param {number} props.marginBottom Margin bottom in px.
 * @param {number} props.paddingTop Padding top in px.
 * @param {number} props.paddingBottom Padding bottom in px.
 * @param {Function} props.setAttributes Gutenberg setAttributes function.
 * @return {JSX.Element} Panel body.
 */
export function SpacingPanel( {
	textdomain = 'laca',
	spacing,
	onChangeSpacing,
	attributeKey = 'spacing',
	marginTop = 0,
	marginBottom = 0,
	paddingTop = 60,
	paddingBottom = 55,
	setAttributes,
} ) {
	if ( spacing && typeof onChangeSpacing === 'function' ) {
		return (
			<PanelBody
				title={ __( 'Spacing', textdomain ) }
				initialOpen={ false }
			>
				<ResponsiveSpacingControls
					textdomain={ textdomain }
					spacing={ spacing }
					onChangeSpacing={ onChangeSpacing }
				/>
			</PanelBody>
		);
	}

	if ( spacing && typeof setAttributes === 'function' ) {
		return (
			<PanelBody
				title={ __( 'Spacing', textdomain ) }
				initialOpen={ false }
			>
				<ResponsiveSpacingControls
					textdomain={ textdomain }
					spacing={ spacing }
					onChangeSpacing={ ( nextSpacing ) =>
						setAttributes( { [ attributeKey ]: nextSpacing } )
					}
				/>
			</PanelBody>
		);
	}

	return (
		<PanelBody title={ __( 'Spacing', textdomain ) } initialOpen={ false }>
			<RangeControl
				label={ __( 'Margin top (px)', textdomain ) }
				value={ marginTop }
				min={ -200 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) => setAttributes( { marginTop: value } ) }
			/>
			<RangeControl
				label={ __( 'Margin bottom (px)', textdomain ) }
				value={ marginBottom }
				min={ -200 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) =>
					setAttributes( { marginBottom: value } )
				}
			/>
			<RangeControl
				label={ __( 'Padding top (px)', textdomain ) }
				value={ paddingTop }
				min={ 0 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) => setAttributes( { paddingTop: value } ) }
			/>
			<RangeControl
				label={ __( 'Padding bottom (px)', textdomain ) }
				value={ paddingBottom }
				min={ 0 }
				max={ 300 }
				step={ 5 }
				onChange={ ( value ) =>
					setAttributes( { paddingBottom: value } )
				}
			/>
		</PanelBody>
	);
}
