export const BLOCK_SPACING_SIDES = [ 'top', 'left', 'bottom', 'right' ];
export const BLOCK_SPACING_TYPES = [ 'margin', 'padding' ];
export const BLOCK_SPACING_DEVICES = [ 'desktop', 'tablet', 'mobile' ];

function cloneSides( values = {} ) {
	return BLOCK_SPACING_SIDES.reduce( ( acc, side ) => {
		acc[ side ] = `${ values?.[ side ] ?? '' }`;
		return acc;
	}, {} );
}

function cloneTypeValues( values = {} ) {
	return BLOCK_SPACING_TYPES.reduce( ( acc, type ) => {
		acc[ type ] = cloneSides( values?.[ type ] || {} );
		return acc;
	}, {} );
}

/**
 * Create the default responsive spacing object shared across blocks.
 *
 * @param {Object} overrides Partial spacing overrides.
 * @return {Object} Fully-populated spacing map.
 */
export function createResponsiveSpacing( overrides = {} ) {
	return BLOCK_SPACING_DEVICES.reduce( ( acc, device ) => {
		acc[ device ] = cloneTypeValues( overrides?.[ device ] || {} );
		return acc;
	}, {} );
}

/**
 * Normalize responsive spacing while preserving any saved values.
 *
 * @param {Object} spacing Saved spacing value.
 * @param {Object} fallback Fallback spacing defaults.
 * @return {Object} Stable spacing object.
 */
export function normalizeResponsiveSpacing(
	spacing = {},
	fallback = createResponsiveSpacing()
) {
	const resolvedFallback = createResponsiveSpacing( fallback );

	if ( ! spacing || typeof spacing !== 'object' ) {
		return resolvedFallback;
	}

	return BLOCK_SPACING_DEVICES.reduce( ( devicesAcc, device ) => {
		const currentDevice = spacing?.[ device ] || {};
		devicesAcc[ device ] = BLOCK_SPACING_TYPES.reduce(
			( typesAcc, type ) => {
				const currentType = currentDevice?.[ type ] || {};
				typesAcc[ type ] = BLOCK_SPACING_SIDES.reduce(
					( sidesAcc, side ) => {
						sidesAcc[ side ] = `${
							currentType?.[ side ] ??
							resolvedFallback[ device ][ type ][ side ]
						}`;
						return sidesAcc;
					},
					{}
				);
				return typesAcc;
			},
			{}
		);
		return devicesAcc;
	}, {} );
}

/**
 * Shared default values for new blocks.
 *
 * @param {Object} overrides Override any default scaffold value.
 * @return {Object} Normalized default values.
 */
export function getDefaultBlockScaffoldValues( overrides = {} ) {
	const values = {
		heading: '',
		subheading: '',
		headingTag: 'h2',
		headingAlign: 'left',
		subheadingAlign: 'left',
		headingColor: '#111111',
		subheadingColor: '#6b7280',
		bgColor: '#0f0f0f',
		bgOpacity: 100,
		spacing: createResponsiveSpacing(),
		marginTop: 0,
		marginBottom: 0,
		paddingTop: 60,
		paddingBottom: 55,
		__isPreview: false,
		...overrides,
	};

	values.spacing = normalizeResponsiveSpacing(
		overrides?.spacing || {},
		overrides?.spacing || createResponsiveSpacing()
	);

	return values;
}

/**
 * Normalize incoming attributes so edit components always receive a stable
 * scaffold shape, even when a legacy block instance is missing some values.
 *
 * @param {Object} attributes Raw Gutenberg attributes.
 * @param {Object} overrides Shared default overrides.
 * @return {Object} Normalized attribute values.
 */
export function normalizeBlockScaffoldAttributes(
	attributes = {},
	overrides = {}
) {
	const defaults = getDefaultBlockScaffoldValues( overrides );
	const nextAttributes = {
		...defaults,
		...( attributes || {} ),
	};

	nextAttributes.heading = `${ nextAttributes.heading ?? defaults.heading }`;
	nextAttributes.subheading = `${
		nextAttributes.subheading ?? defaults.subheading
	}`;
	nextAttributes.headingTag = `${ nextAttributes.headingTag ?? defaults.headingTag }`;
	nextAttributes.headingAlign = `${
		nextAttributes.headingAlign ?? defaults.headingAlign
	}`;
	nextAttributes.subheadingAlign = `${
		nextAttributes.subheadingAlign ?? defaults.subheadingAlign
	}`;
	nextAttributes.headingColor = `${
		nextAttributes.headingColor ?? defaults.headingColor
	}`;
	nextAttributes.subheadingColor = `${
		nextAttributes.subheadingColor ?? defaults.subheadingColor
	}`;
	nextAttributes.bgColor = `${ nextAttributes.bgColor ?? defaults.bgColor }`;
	nextAttributes.bgOpacity = Number.isFinite(
		Number( nextAttributes.bgOpacity )
	)
		? Number( nextAttributes.bgOpacity )
		: defaults.bgOpacity;
	nextAttributes.spacing = normalizeResponsiveSpacing(
		nextAttributes.spacing,
		defaults.spacing
	);

	return nextAttributes;
}
