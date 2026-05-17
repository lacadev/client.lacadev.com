function appendQueryString( baseUrl, params = {} ) {
	const searchParams = new URLSearchParams();

	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value === undefined || value === null || value === '' ) {
			return;
		}

		searchParams.set( key, `${ value }` );
	} );

	const queryString = searchParams.toString();

	if ( ! queryString ) {
		return baseUrl;
	}

	return `${ baseUrl }?${ queryString }`;
}

function parseYouTubeId( normalizedUrl ) {
	const youtubeMatch = normalizedUrl.match(
		/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/
	);

	return youtubeMatch?.[ 1 ] || '';
}

function parseVimeoId( normalizedUrl ) {
	const vimeoMatch = normalizedUrl.match( /vimeo\.com\/(?:video\/)?(\d+)/ );

	return vimeoMatch?.[ 1 ] || '';
}

/**
 * Parse a video URL into provider metadata.
 *
 * @param {string} url Video URL from block attributes.
 * @return {Object} Parsed video metadata.
 */
export function parseVideoUrl( url = '' ) {
	const normalizedUrl = `${ url || '' }`.trim();

	if ( ! normalizedUrl ) {
		return {
			provider: 'unknown',
			videoId: '',
			normalizedUrl: '',
			embedUrl: '',
		};
	}

	const youtubeId = parseYouTubeId( normalizedUrl );
	if ( youtubeId ) {
		return {
			provider: 'youtube',
			videoId: youtubeId,
			normalizedUrl,
			embedUrl: `https://www.youtube.com/embed/${ youtubeId }`,
		};
	}

	const vimeoId = parseVimeoId( normalizedUrl );
	if ( vimeoId ) {
		return {
			provider: 'vimeo',
			videoId: vimeoId,
			normalizedUrl,
			embedUrl: `https://player.vimeo.com/video/${ vimeoId }`,
		};
	}

	return {
		provider: 'direct',
		videoId: '',
		normalizedUrl,
		embedUrl: normalizedUrl,
	};
}

/**
 * Build the final embed URL with common player params.
 *
 * @param {string} url Video URL from block attributes.
 * @param {Object} options Embed options.
 * @param {boolean} options.autoplay Autoplay flag.
 * @param {boolean} options.loop Loop flag.
 * @param {boolean} options.muted Muted flag.
 * @param {boolean} options.controls Controls flag.
 * @return {string} Embed URL or direct URL.
 */
export function getVideoEmbedUrl( url = '', options = {} ) {
	const parsed = parseVideoUrl( url );

	if ( ! parsed.embedUrl ) {
		return '';
	}

	const {
		autoplay = false,
		loop = false,
		muted = false,
		controls = true,
	} = options;

	if ( parsed.provider === 'youtube' ) {
		return appendQueryString( parsed.embedUrl, {
			autoplay: autoplay ? 1 : 0,
			controls: controls ? 1 : 0,
			loop: loop ? 1 : 0,
			mute: muted ? 1 : 0,
			modestbranding: 1,
			playsinline: 1,
			rel: 0,
			playlist: loop ? parsed.videoId : '',
		} );
	}

	if ( parsed.provider === 'vimeo' ) {
		return appendQueryString( parsed.embedUrl, {
			autoplay: autoplay ? 1 : 0,
			loop: loop ? 1 : 0,
			muted: muted ? 1 : 0,
			controls: controls ? 1 : 0,
		} );
	}

	return parsed.embedUrl;
}

/**
 * Build a stable preview model for the block editor.
 *
 * @param {string} url Video URL from block attributes.
 * @return {Object} Preview metadata.
 */
export function getVideoEditorPreview( url = '' ) {
	const parsed = parseVideoUrl( url );

	if ( parsed.provider === 'youtube' ) {
		return {
			type: 'image',
			providerLabel: 'YouTube',
			title: 'Xem trước video YouTube',
			thumbnailUrl: `https://i.ytimg.com/vi/${ parsed.videoId }/hqdefault.jpg`,
			embedUrl: parsed.embedUrl,
			sourceUrl: parsed.normalizedUrl,
		};
	}

	if ( parsed.provider === 'vimeo' ) {
		return {
			type: 'card',
			providerLabel: 'Vimeo',
			title: 'Xem trước video Vimeo',
			thumbnailUrl: '',
			embedUrl: parsed.embedUrl,
			sourceUrl: parsed.normalizedUrl,
		};
	}

	if ( parsed.provider === 'direct' ) {
		return {
			type: 'video',
			providerLabel: 'Video URL',
			title: 'Xem trước video trực tiếp',
			thumbnailUrl: '',
			embedUrl: parsed.embedUrl,
			sourceUrl: parsed.normalizedUrl,
		};
	}

	return {
		type: 'empty',
		providerLabel: '',
		title: '',
		thumbnailUrl: '',
		embedUrl: '',
		sourceUrl: '',
	};
}
