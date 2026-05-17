import {
	BLOCK_SPACING_DEVICES,
	BLOCK_SPACING_SIDES,
	BLOCK_SPACING_TYPES,
} from './block-defaults';

/**
 * Convert hex color + opacity percentage to rgba string.
 *
 * @param {string} hex Hex color in #RRGGBB format.
 * @param {number} opacity Opacity in percent (0-100).
 * @return {string} RGBA color string.
 */
export function hexToRgba( hex, opacity = 100 ) {
	if ( ! hex || ! /^#[0-9A-Fa-f]{6}$/.test( hex ) ) {
		return `rgba(15,15,15,${
			Math.max( 0, Math.min( 100, opacity ) ) / 100
		})`;
	}

	const normalizedOpacity = Math.max( 0, Math.min( 100, opacity ) ) / 100;
	const r = Number.parseInt( hex.slice( 1, 3 ), 16 );
	const g = Number.parseInt( hex.slice( 3, 5 ), 16 );
	const b = Number.parseInt( hex.slice( 5, 7 ), 16 );

	return `rgba(${ r },${ g },${ b },${ normalizedOpacity })`;
}

/**
 * Build responsive spacing CSS vars from spacing object.
 *
 * @param {Object} spacing Responsive spacing config.
 * @param {string} cssVarPrefix Prefix for CSS vars.
 * @return {Object} CSS variable map.
 */
export function getSpacingVars( spacing = {}, cssVarPrefix = '--laca-video' ) {
	const vars = {};

	BLOCK_SPACING_DEVICES.forEach( ( device ) => {
		BLOCK_SPACING_TYPES.forEach( ( type ) => {
			BLOCK_SPACING_SIDES.forEach( ( side ) => {
				const value = `${
					spacing?.[ device ]?.[ type ]?.[ side ] ?? ''
				}`.trim();
				if ( value ) {
					const suffix = device === 'desktop' ? '' : `-${ device }`;
					vars[ `${ cssVarPrefix }-${ type }-${ side }${ suffix }` ] =
						value;
				}
			} );
		} );
	} );

	return vars;
}
