export type DisplayDirection = 'auto' | 'ltr' | 'rtl';
export type DisplayAudience =
	| 'all'
	| 'authenticated'
	| 'anonymous'
	| 'selected_roles';
export type WooArea = 'shop' | 'product' | 'cart' | 'checkout' | 'account';
export type DeviceBucket = 'desktop' | 'tablet' | 'mobile';

export type DisplaySchedule = {
	timezone: 'site';
	days: readonly number[];
	start: string | null;
	end: string | null;
};

export type DisplayRulesConfig = {
	enabled: boolean;
	visibility: {
		url_include: readonly string[];
		url_exclude: readonly string[];
		post_types: readonly string[];
		audience: DisplayAudience;
		roles: readonly string[];
		woo_areas: readonly WooArea[];
		devices: readonly DeviceBucket[];
		schedule?: DisplaySchedule;
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
	isAuthenticated?: boolean;
	roleMatches?: readonly string[];
	postType?: string;
	wooArea?: WooArea;
	device?: DeviceBucket;
	siteWeekday?: number;
	siteMinuteOfDay?: number;
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
const MAX_SLUG_VALUES = 16;
const MAX_SLUG_LENGTH = 64;
const SLUG_PATTERN = /^[a-z0-9_-]+$/;
const TIME_PATTERN = /^(?:[01]\d|2[0-3]):[0-5]\d$/;
const AUDIENCES: readonly DisplayAudience[] = [
	'all',
	'authenticated',
	'anonymous',
	'selected_roles',
];
const WOO_AREAS: readonly WooArea[] = [
	'shop',
	'product',
	'cart',
	'checkout',
	'account',
];
const DEVICE_BUCKETS: readonly DeviceBucket[] = [
	'desktop',
	'tablet',
	'mobile',
];

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

const normalizeSlugList = ( value: unknown ): readonly string[] => {
	if ( ! Array.isArray( value ) ) {
		return [];
	}

	const slugs: string[] = [];
	for ( const candidate of value ) {
		if ( typeof candidate !== 'string' ) {
			continue;
		}

		const normalized = candidate.trim().toLowerCase();
		if (
			normalized === '' ||
			normalized.length > MAX_SLUG_LENGTH ||
			! SLUG_PATTERN.test( normalized ) ||
			slugs.includes( normalized )
		) {
			continue;
		}

		slugs.push( normalized );
		if ( slugs.length >= MAX_SLUG_VALUES ) {
			break;
		}
	}

	return slugs;
};

const normalizeChoiceList = < T extends string >(
	value: unknown,
	allowed: readonly T[]
): readonly T[] => {
	if ( ! Array.isArray( value ) ) {
		return [];
	}

	const choices: T[] = [];
	for ( const candidate of value ) {
		if (
			typeof candidate === 'string' &&
			allowed.includes( candidate as T ) &&
			! choices.includes( candidate as T )
		) {
			choices.push( candidate as T );
		}
	}

	return choices;
};

const normalizeAudience = ( value: unknown ): DisplayAudience =>
	typeof value === 'string' && AUDIENCES.includes( value as DisplayAudience )
		? ( value as DisplayAudience )
		: 'all';

const normalizeSchedule = ( value: unknown ): DisplaySchedule | undefined => {
	if ( typeof value !== 'object' || value === null || Array.isArray( value ) ) {
		return undefined;
	}

	const candidate = value as Record< string, unknown >;
	const days: number[] = [];
	if ( Array.isArray( candidate.days ) ) {
		for ( const day of candidate.days ) {
			if (
				typeof day === 'number' &&
				Number.isInteger( day ) &&
				day >= 0 &&
				day <= 6 &&
				! days.includes( day )
			) {
				days.push( day );
			}
		}
	}

	const start =
		typeof candidate.start === 'string' && TIME_PATTERN.test( candidate.start )
			? candidate.start
			: null;
	const end =
		typeof candidate.end === 'string' && TIME_PATTERN.test( candidate.end )
			? candidate.end
			: null;

	return {
		timezone: 'site',
		days,
		start,
		end,
	};
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

		if (
			patternIndex < pattern.length &&
			pattern[ patternIndex ] === '*'
		) {
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

const timeToMinute = ( value: string ): number => {
	const [ hours, minutes ] = value.split( ':' ).map( Number );
	return hours * 60 + minutes;
};

const scheduleMatches = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts
): boolean => {
	const schedule = config.visibility.schedule;
	if ( schedule === undefined ) {
		return true;
	}

	const weekday = facts.siteWeekday;
	const minute = facts.siteMinuteOfDay;
	if (
		typeof weekday !== 'number' ||
		! Number.isInteger( weekday ) ||
		weekday < 0 ||
		weekday > 6 ||
		typeof minute !== 'number' ||
		! Number.isInteger( minute ) ||
		minute < 0 ||
		minute > 1439
	) {
		return false;
	}

	const dayMatches = ( day: number ): boolean =>
		schedule.days.length === 0 || schedule.days.includes( day );
	const start = schedule.start === null ? null : timeToMinute( schedule.start );
	const end = schedule.end === null ? null : timeToMinute( schedule.end );

	if ( start === null && end === null ) {
		return dayMatches( weekday );
	}

	if ( start !== null && end === null ) {
		return dayMatches( weekday ) && minute >= start;
	}

	if ( start === null && end !== null ) {
		return dayMatches( weekday ) && minute <= end;
	}

	if ( start === null || end === null ) {
		return false;
	}

	if ( start <= end ) {
		return dayMatches( weekday ) && minute >= start && minute <= end;
	}

	const previousWeekday = ( weekday + 6 ) % 7;
	return (
		( minute >= start && dayMatches( weekday ) ) ||
		( minute <= end && dayMatches( previousWeekday ) )
	);
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

const audienceMatches = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts
): boolean => {
	switch ( config.visibility.audience ) {
		case 'authenticated':
			return facts.isAuthenticated === true;
		case 'anonymous':
			return facts.isAuthenticated === false;
		case 'selected_roles':
			return (
				facts.isAuthenticated === true &&
				config.visibility.roles.length > 0 &&
				Array.isArray( facts.roleMatches ) &&
				facts.roleMatches.some( ( role ) =>
					config.visibility.roles.includes( role )
				)
			);
		case 'all':
		default:
			return true;
	}
};

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
	const schedule = normalizeSchedule( visibility.schedule );

	return {
		enabled: candidate.enabled !== false,
		visibility: {
			url_include: urlInclude,
			url_exclude: urlExclude,
			post_types: normalizeSlugList( visibility.post_types ),
			audience: normalizeAudience( visibility.audience ),
			roles: normalizeSlugList( visibility.roles ),
			woo_areas: normalizeChoiceList( visibility.woo_areas, WOO_AREAS ),
			devices: normalizeChoiceList( visibility.devices, DEVICE_BUCKETS ),
			...( schedule === undefined ? {} : { schedule } ),
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

	const reasons = [ 'enabled' ];
	if ( config.visibility.url_include.length > 0 ) {
		const included = config.visibility.url_include.some( ( pattern ) =>
			pathMatchesPattern( path, pattern )
		);
		if ( ! included ) {
			return baseDecision( config, false, [ 'url_not_included' ] );
		}
		reasons.push( 'url_included' );
	}

	if ( ! audienceMatches( config, facts ) ) {
		return baseDecision( config, false, [ 'audience_mismatch' ] );
	}

	if (
		config.visibility.post_types.length > 0 &&
		( typeof facts.postType !== 'string' ||
			! config.visibility.post_types.includes( facts.postType ) )
	) {
		return baseDecision( config, false, [ 'post_type_mismatch' ] );
	}

	if (
		config.visibility.woo_areas.length > 0 &&
		( facts.wooArea === undefined ||
			! config.visibility.woo_areas.includes( facts.wooArea ) )
	) {
		return baseDecision( config, false, [ 'woo_area_mismatch' ] );
	}

	if (
		config.visibility.devices.length > 0 &&
		( facts.device === undefined ||
			! config.visibility.devices.includes( facts.device ) )
	) {
		return baseDecision( config, false, [ 'device_mismatch' ] );
	}

	if ( ! scheduleMatches( config, facts ) ) {
		return baseDecision( config, false, [ 'schedule_mismatch' ] );
	}

	return baseDecision( config, true, reasons );
};
