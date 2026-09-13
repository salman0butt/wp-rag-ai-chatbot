export type DisplayDirection = 'auto' | 'ltr' | 'rtl';

export type DisplayRulesConfig = {
	enabled: boolean;
	visibility: {
		url_include: readonly string[];
		url_exclude: readonly string[];
	};
	proactive: {
		enabled: boolean;
	};
	starters: {
		default: readonly string[];
		by_page: readonly unknown[];
	};
	localization: {
		locale: string;
		direction: DisplayDirection;
	};
};

export type DisplayRuleFacts = {
	path?: string;
};

export type DisplayDecision = {
	visible: boolean;
	proactiveEligible: boolean;
	starters: readonly string[];
	locale: string;
	direction: 'ltr' | 'rtl';
	reasons: readonly string[];
};

const MAX_URL_PATTERNS = 32;
const MAX_URL_PATTERN_LENGTH = 256;
const MAX_URL_WILDCARDS = 4;

const asRecord = ( value: unknown ): Record< string, unknown > =>
	typeof value === 'object' && value !== null
		? ( value as Record< string, unknown > )
		: {};

const normalizePathPattern = ( value: unknown ): string | null => {
	if ( typeof value !== 'string' ) {
		return null;
	}

	let normalized = value.trim();
	if (
		normalized === '' ||
		normalized.length > MAX_URL_PATTERN_LENGTH ||
		[ ...normalized ].filter( ( character ) => character === '*' ).length >
			MAX_URL_WILDCARDS
	) {
		return null;
	}

	if ( normalized[ 0 ] !== '/' && normalized[ 0 ] !== '*' ) {
		normalized = `/${ normalized }`;
	}

	return normalized;
};

const normalizePatterns = (
	value: unknown,
	remaining: number
): readonly string[] => {
	if ( ! Array.isArray( value ) || remaining <= 0 ) {
		return [];
	}

	const patterns: string[] = [];
	for ( const candidate of value ) {
		const normalized = normalizePathPattern( candidate );
		if ( normalized === null || patterns.includes( normalized ) ) {
			continue;
		}

		patterns.push( normalized );
		if ( patterns.length >= remaining ) {
			break;
		}
	}

	return patterns;
};

const pathMatchesPattern = ( path: string, pattern: string ): boolean => {
	let pathIndex = 0;
	let patternIndex = 0;
	let starIndex = -1;
	let starPathIndex = -1;

	while ( pathIndex < path.length ) {
		if (
			patternIndex < pattern.length &&
			pattern[ patternIndex ] === path[ pathIndex ]
		) {
			pathIndex += 1;
			patternIndex += 1;
			continue;
		}

		if ( patternIndex < pattern.length && pattern[ patternIndex ] === '*' ) {
			starIndex = patternIndex;
			starPathIndex = pathIndex;
			patternIndex += 1;
			continue;
		}

		if ( starIndex !== -1 ) {
			patternIndex = starIndex + 1;
			starPathIndex += 1;
			pathIndex = starPathIndex;
			continue;
		}

		return false;
	}

	while ( patternIndex < pattern.length && pattern[ patternIndex ] === '*' ) {
		patternIndex += 1;
	}

	return patternIndex === pattern.length;
};

const normalizeFactPath = ( value: unknown ): string => {
	if ( typeof value !== 'string' ) {
		return '/';
	}

	const trimmed = value.trim();
	if ( trimmed === '' ) {
		return '/';
	}

	return trimmed[ 0 ] === '/' ? trimmed : `/${ trimmed }`;
};

const baseDecision = (
	config: DisplayRulesConfig,
	visible: boolean,
	reasons: readonly string[]
): DisplayDecision => ( {
	visible,
	proactiveEligible: false,
	starters: [],
	locale: config.localization.locale,
	direction: config.localization.direction === 'rtl' ? 'rtl' : 'ltr',
	reasons,
} );

export const normalizeDisplayRules = ( value: unknown ): DisplayRulesConfig => {
	const candidate = asRecord( value );
	const visibility = asRecord( candidate.visibility );
	const urlInclude = normalizePatterns(
		visibility.url_include,
		MAX_URL_PATTERNS
	);
	const urlExclude = normalizePatterns(
		visibility.url_exclude,
		MAX_URL_PATTERNS - urlInclude.length
	);

	return {
		enabled: candidate.enabled !== false,
		visibility: {
			url_include: urlInclude,
			url_exclude: urlExclude,
		},
		proactive: {
			enabled: false,
		},
		starters: {
			default: [],
			by_page: [],
		},
		localization: {
			locale: 'site',
			direction: 'auto',
		},
	};
};

export const evaluateDisplayRules = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts = {}
): DisplayDecision => {
	if ( ! config.enabled ) {
		return baseDecision( config, false, [ 'disabled' ] );
	}

	const path = normalizeFactPath( facts.path );
	if (
		config.visibility.url_exclude.some( ( pattern ) =>
			pathMatchesPattern( path, pattern )
		)
	) {
		return baseDecision( config, false, [ 'url_excluded' ] );
	}

	if ( config.visibility.url_include.length > 0 ) {
		const included = config.visibility.url_include.some( ( pattern ) =>
			pathMatchesPattern( path, pattern )
		);

		return included
			? baseDecision( config, true, [ 'enabled', 'url_included' ] )
			: baseDecision( config, false, [ 'url_not_included' ] );
	}

	return baseDecision( config, true, [ 'enabled' ] );
};
