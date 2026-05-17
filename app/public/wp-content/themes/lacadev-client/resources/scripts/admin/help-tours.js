( function () {
	'use strict';

	const data = window.lacaHelpToursData;
	if ( ! data || ! Array.isArray( data.tours ) || data.tours.length === 0 ) {
		return;
	}

	const query = new URLSearchParams( window.location.search );
	const activeSlug = query.get( 'laca_tour' );
	if ( ! activeSlug ) {
		return;
	}

	const tour = data.tours.find( ( item ) => item.slug === activeSlug );
	if ( ! tour || ! Array.isArray( tour.steps ) ) {
		clearTourQuery();
		return;
	}

	const i18n = Object.assign(
		{
			next: 'Next',
			prev: 'Previous',
			done: 'Done',
			skip: 'Skip',
			missingSteps: 'No steps available for this screen.',
			missingStep: 'This step could not be found on the current screen.',
			missingStepHint:
				'The selector for this step was not found. You can still continue the tour.',
			stepLabel: 'Step',
		},
		data.i18n || {}
	);

			function normalizeSelector( selector ) {
		return String( selector )
			.replaceAll( '\u201C', '"' )
			.replaceAll( '\u201D', '"' )
			.replaceAll( '\u2018', "'" )
			.replaceAll( '\u2019', "'" )
			.replaceAll( 'â€œ', '"' )
			.replaceAll( 'â€', '"' )
			.replaceAll( 'â€˜', "'" )
			.replaceAll( 'â€™', "'" )
			.trim();
	}
	function stripWrappedQuotes( value ) {
		const normalizedValue = normalizeSelector( value ).trim();
		if ( normalizedValue.length < 2 ) {
			return normalizedValue;
		}

		const firstChar = normalizedValue.charAt( 0 );
		const lastChar = normalizedValue.charAt( normalizedValue.length - 1 );
		if (
			( firstChar === '"' && lastChar === '"' ) ||
			( firstChar === "'" && lastChar === "'" )
		) {
			return normalizedValue.slice( 1, -1 );
		}

		return normalizedValue;
	}

	function normalizeAttributeValue( value ) {
		return normalizeSelector( value )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function resolveAttributeSelector( selector ) {
		const match = normalizeSelector( selector ).match(
			/^\s*([a-z0-9:_-]+)?\s*\[\s*([^\]=\s]+)\s*=\s*(.+)\s*\]\s*$/i
		);
		if ( ! match ) {
			return null;
		}

		const tagName = ( match[ 1 ] || '' ).trim();
		const attributeName = match[ 2 ].trim();
		const attributeValue = normalizeAttributeValue(
			stripWrappedQuotes( match[ 3 ] )
		);
		if ( ! attributeName || ! attributeValue ) {
			return null;
		}

		const query = tagName
			? `${ tagName }[${ attributeName }]`
			: `[${ attributeName }]`;
		const candidates = document.querySelectorAll( query );

		return (
			Array.from( candidates ).find(
				( element ) =>
					normalizeAttributeValue( element.getAttribute( attributeName ) ) ===
					attributeValue
			) || null
		);
	}

	function resolveElement( selector ) {
		const normalizedSelector = normalizeSelector( selector );

		try {
			const directMatch = document.querySelector( normalizedSelector );
			if ( directMatch ) {
				return directMatch;
			}
		} catch ( error ) {
			console.warn(
				'Invalid help tour selector:',
				selector,
				'Normalized:',
				normalizedSelector,
				error
			);
		}

		return resolveAttributeSelector( normalizedSelector );
	}

	const normalizedSteps = tour.steps
		.map( ( step ) => {
			if ( ! step ) {
				return null;
			}

			return {
				title: step.title || '',
				content: step.content || '',
				position: step.position || 'bottom',
				clickUrl: step.click_url || '',
				selector: normalizeSelector( step.selector || '' ),
			};
		} )
		.filter( Boolean );

	if ( normalizedSteps.length === 0 ) {
		notify( i18n.missingSteps );
		clearTourQuery();
		return;
	}

	const requestedStep = Number.parseInt( query.get( 'laca_tour_step' ) || '0', 10 );
	let currentIndex =
		Number.isFinite( requestedStep ) && requestedStep >= 0
			? Math.min( requestedStep, normalizedSteps.length - 1 )
			: 0;
	let overlay = null;
	let tooltip = null;
	let titleEl = null;
	let bodyEl = null;
	let countEl = null;
	let prevBtn = null;
	let nextBtn = null;
	let statusEl = null;
	let currentElement = null;
	let currentActionElement = null;
	let currentActionHandler = null;

	function escapeHtml( value ) {
		return String( value )
			.replaceAll( '&', '&amp;' )
			.replaceAll( '<', '&lt;' )
			.replaceAll( '>', '&gt;' )
			.replaceAll( '"', '&quot;' )
			.replaceAll( "'", '&#039;' );
	}

	function notify( message ) {
		if ( window.Swal ) {
			window.Swal.fire( {
				text: message,
				icon: 'info',
				confirmButtonText: 'OK',
			} );
			return;
		}

		window.alert( message );
	}

	function clearTourQuery() {
		const params = new URLSearchParams( window.location.search );
		params.delete( 'laca_tour' );
		params.delete( 'laca_tour_step' );
		const nextQuery = params.toString();
		const nextUrl =
			window.location.pathname +
			( nextQuery ? `?${ nextQuery }` : '' ) +
			window.location.hash;
		window.history.replaceState( {}, document.title, nextUrl );
	}

	function teardown() {
		document.body.classList.remove( 'laca-help-tour-active' );
		document
			.querySelectorAll( '.laca-help-tour-target' )
			.forEach( ( node ) => node.classList.remove( 'laca-help-tour-target' ) );
		detachActionListener();

		if ( overlay ) {
			overlay.remove();
		}

		window.removeEventListener( 'resize', positionTooltip );
		window.removeEventListener( 'scroll', positionTooltip, true );
		document.removeEventListener( 'keydown', onKeydown );
		overlay = null;
		clearTourQuery();
	}

	function setTourStepQuery( index ) {
		const params = new URLSearchParams( window.location.search );
		params.set( 'laca_tour', activeSlug );
		params.set( 'laca_tour_step', String( index ) );
		const nextQuery = params.toString();
		const nextUrl =
			window.location.pathname +
			( nextQuery ? `?${ nextQuery }` : '' ) +
			window.location.hash;
		window.history.replaceState( {}, document.title, nextUrl );
	}

	function preferredPosition( step ) {
		if ( step.position && step.position !== 'auto' ) {
			return step.position;
		}

		const rect = currentElement.getBoundingClientRect();
		const viewportHeight = window.innerHeight;
		const viewportWidth = window.innerWidth;
		const spaceTop = rect.top;
		const spaceBottom = viewportHeight - rect.bottom;
		const spaceRight = viewportWidth - rect.right;
		const spaceLeft = rect.left;

		if ( spaceBottom >= 220 ) {
			return 'bottom';
		}
		if ( spaceTop >= 220 ) {
			return 'top';
		}
		if ( spaceRight >= 320 ) {
			return 'right';
		}
		if ( spaceLeft >= 320 ) {
			return 'left';
		}

		return 'bottom';
	}

	function positionTooltip() {
		const step = normalizedSteps[ currentIndex ];
		if ( ! step || ! tooltip ) {
			return;
		}

		if ( ! currentElement ) {
			tooltip.style.left = `${ Math.max(
				12,
				( window.innerWidth - tooltip.offsetWidth ) / 2
			) }px`;
			tooltip.style.top = `${ Math.max(
				12,
				( window.innerHeight - tooltip.offsetHeight ) / 2
			) }px`;
			return;
		}

		const rect = currentElement.getBoundingClientRect();
		const tipRect = tooltip.getBoundingClientRect();
		const gap = 16;
		const position = preferredPosition( step );
		let top = 0;
		let left = 0;

		if ( position === 'top' ) {
			top = rect.top - tipRect.height - gap;
			left = rect.left + ( rect.width / 2 ) - ( tipRect.width / 2 );
		} else if ( position === 'left' ) {
			top = rect.top + ( rect.height / 2 ) - ( tipRect.height / 2 );
			left = rect.left - tipRect.width - gap;
		} else if ( position === 'right' ) {
			top = rect.top + ( rect.height / 2 ) - ( tipRect.height / 2 );
			left = rect.right + gap;
		} else {
			top = rect.bottom + gap;
			left = rect.left + ( rect.width / 2 ) - ( tipRect.width / 2 );
		}

		const maxLeft = Math.max( 12, window.innerWidth - tipRect.width - 12 );
		const maxTop = Math.max( 12, window.innerHeight - tipRect.height - 12 );

		tooltip.style.left = `${ Math.min( maxLeft, Math.max( 12, left ) ) }px`;
		tooltip.style.top = `${ Math.min( maxTop, Math.max( 12, top ) ) }px`;
	}

	function getActionElement( element ) {
		if ( ! element ) {
			return null;
		}

		if (
			element.matches(
				'a[href], button, [role="button"], [aria-controls], input[type="button"], input[type="submit"]'
			)
		) {
			return element;
		}

		return element.querySelector(
			'a[href], button, [role="button"], [aria-controls], input[type="button"], input[type="submit"]'
		);
	}

	function buildStepUrl( rawUrl, nextIndex ) {
		try {
			const url = new URL( rawUrl, window.location.origin );
			url.searchParams.set( 'laca_tour', activeSlug );
			url.searchParams.set( 'laca_tour_step', String( nextIndex ) );
			return url.toString();
		} catch ( error ) {
			return '';
		}
	}

	function getContinuationUrl() {
		if ( currentIndex >= normalizedSteps.length - 1 ) {
			return '';
		}

		const step = normalizedSteps[ currentIndex ];
		if ( step && step.clickUrl ) {
			return buildStepUrl( step.clickUrl, currentIndex + 1 );
		}

		const actionElement = getActionElement( currentElement );
		if ( ! actionElement ) {
			return '';
		}

		return buildStepUrl( actionElement.href, currentIndex + 1 );
	}

	function detachActionListener() {
		if ( currentActionElement && currentActionHandler ) {
			currentActionElement.removeEventListener( 'click', currentActionHandler );
		}

		currentActionElement = null;
		currentActionHandler = null;
	}

	function attachActionListener() {
		detachActionListener();

		const step = normalizedSteps[ currentIndex ];
		currentActionElement = getActionElement( currentElement );
		if ( ! currentActionElement ) {
			return;
		}

		const continuationUrl = getContinuationUrl();
		if ( continuationUrl ) {
			currentActionHandler = ( event ) => {
				event.preventDefault();
				window.location.assign( continuationUrl );
			};
		} else if (
			step &&
			currentIndex < normalizedSteps.length - 1 &&
			! currentActionElement.matches( 'a[href]' )
		) {
			const stepIndex = currentIndex;
			currentActionHandler = () => {
				window.setTimeout( () => {
					if ( currentIndex === stepIndex ) {
						goToStep( stepIndex + 1 );
					}
				}, 180 );
			};
		} else {
			return;
		}

		currentActionElement.addEventListener( 'click', currentActionHandler );
	}

	function goToStep( nextIndex ) {
		currentIndex = nextIndex;
		setTourStepQuery( currentIndex );
		renderStep();
	}

	function renderStep() {
		const step = normalizedSteps[ currentIndex ];
		if ( ! step ) {
			teardown();
			return;
		}

		document
			.querySelectorAll( '.laca-help-tour-target' )
			.forEach( ( node ) => node.classList.remove( 'laca-help-tour-target' ) );

		currentElement = step.selector ? resolveElement( step.selector ) : null;
		if ( currentElement ) {
			currentElement.classList.add( 'laca-help-tour-target' );
			currentElement.scrollIntoView( {
				behavior: 'smooth',
				block: 'center',
				inline: 'center',
			} );
			tooltip.classList.remove( 'is-floating' );
			statusEl.hidden = true;
			statusEl.textContent = '';
		} else {
			tooltip.classList.add( 'is-floating' );
			statusEl.hidden = false;
			statusEl.textContent = step.selector
				? `${ i18n.missingStepHint } (${ step.selector })`
				: i18n.missingStepHint;
		}

		attachActionListener();

		titleEl.textContent = step.title || tour.title;
		bodyEl.innerHTML = escapeHtml( step.content ).replaceAll( '\n', '<br>' );
		countEl.textContent = `${ i18n.stepLabel } ${ currentIndex + 1 } / ${ normalizedSteps.length }`;
		prevBtn.disabled = currentIndex === 0;
		nextBtn.textContent =
			currentIndex === normalizedSteps.length - 1 ? i18n.done : i18n.next;
		setTourStepQuery( currentIndex );

		window.setTimeout( positionTooltip, 180 );
	}

	function buildUi() {
		overlay = document.createElement( 'div' );
		overlay.className = 'laca-help-tour-overlay';
		overlay.innerHTML = `
			<div class="laca-help-tour-tooltip" role="dialog" aria-modal="true">
				<div class="laca-help-tour-tooltip__count"></div>
				<h3 class="laca-help-tour-tooltip__title"></h3>
				<div class="laca-help-tour-tooltip__status" hidden></div>
				<div class="laca-help-tour-tooltip__body"></div>
				<div class="laca-help-tour-tooltip__actions">
					<button type="button" class="button button-secondary" data-tour-prev>${ escapeHtml( i18n.prev ) }</button>
					<div class="laca-help-tour-tooltip__actions-right">
						<button type="button" class="button button-link-delete" data-tour-skip>${ escapeHtml( i18n.skip ) }</button>
						<button type="button" class="button button-primary" data-tour-next>${ escapeHtml( i18n.next ) }</button>
					</div>
				</div>
			</div>
		`;

		document.body.appendChild( overlay );
		document.body.classList.add( 'laca-help-tour-active' );

		tooltip = overlay.querySelector( '.laca-help-tour-tooltip' );
		titleEl = overlay.querySelector( '.laca-help-tour-tooltip__title' );
		statusEl = overlay.querySelector( '.laca-help-tour-tooltip__status' );
		bodyEl = overlay.querySelector( '.laca-help-tour-tooltip__body' );
		countEl = overlay.querySelector( '.laca-help-tour-tooltip__count' );
		prevBtn = overlay.querySelector( '[data-tour-prev]' );
		nextBtn = overlay.querySelector( '[data-tour-next]' );

		overlay
			.querySelector( '[data-tour-skip]' )
			.addEventListener( 'click', teardown );
		prevBtn.addEventListener( 'click', () => {
			if ( currentIndex > 0 ) {
				goToStep( currentIndex - 1 );
			}
		} );
		nextBtn.addEventListener( 'click', () => {
			if ( currentIndex >= normalizedSteps.length - 1 ) {
				teardown();
				return;
			}

			const continuationUrl = getContinuationUrl();
			if ( continuationUrl ) {
				window.location.assign( continuationUrl );
				return;
			}

			if ( currentIndex < normalizedSteps.length - 1 ) {
				goToStep( currentIndex + 1 );
				return;
			}

			notify( i18n.missingSteps );
			teardown();
		} );

		window.addEventListener( 'resize', positionTooltip );
		window.addEventListener( 'scroll', positionTooltip, true );
		document.addEventListener( 'keydown', onKeydown );
	}

	function onKeydown( event ) {
		if ( ! overlay ) {
			document.removeEventListener( 'keydown', onKeydown );
			return;
		}

		if ( event.key === 'Escape' ) {
			teardown();
		}
	}

	function init() {
		buildUi();
		renderStep();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
