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

export type StarterRule = {
	pattern: string;
	prompts: readonly string[];
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
		by_page: readonly StarterRule[];
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
	siteLocale?: string;
	documentLocale?: string;
	siteDirection?: 'ltr' | 'rtl';
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
const MAX_STARTER_MAPPINGS = 8;
const MAX_STARTER_PROMPTS = 4;
const MAX_PROMPT_CODEPOINTS = 160;
const MAX_LOCALE_LENGTH = 35;
const SLUG_PATTERN = /^[a-z0-9_-]+$/;
const TIME_PATTERN = /^(?:[01]\d|2[0-3]):[0-5]\d$/;
const LOCALE_PATTERN = /^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/;
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
const RTL_LOCALES = [ 'ar', 'fa', 'he', 'ur' ] as const;

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
	if (
		typeof value !== 'object' ||
		value === null ||
		Array.isArray( value )
	) {
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
		typeof candidate.start === 'string' &&
		TIME_PATTERN.test( candidate.start )
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

const normalizePromptList = ( value: unknown ): readonly string[] => {
	if ( ! Array.isArray( value ) ) {
		return [];
	}

	const prompts: string[] = [];
	for ( const candidate of value ) {
		if ( typeof candidate !== 'string' ) {
			continue;
		}

		const normalized = candidate.trim();
		if (
			normalized === '' ||
			[ ...normalized ].length > MAX_PROMPT_CODEPOINTS
		) {
			continue;
		}

		prompts.push( normalized );
		if ( prompts.length >= MAX_STARTER_PROMPTS ) {
			break;
		}
	}

	return prompts;
};

const normalizeStarterRules = ( value: unknown ): readonly StarterRule[] => {
	if ( ! Array.isArray( value ) ) {
		return [];
	}

	const rules: StarterRule[] = [];
	for ( const candidate of value ) {
		if ( rules.length >= MAX_STARTER_MAPPINGS ) {
			break;
		}

		const record = asRecord( candidate );
		const pattern = normalizePathPattern( record.pattern );
		const prompts = normalizePromptList( record.prompts );
		if ( pattern === null || prompts.length === 0 ) {
			continue;
		}

		rules.push( { pattern, prompts } );
	}

	return rules;
};

const normalizeLocaleToken = ( value: unknown ): string | null => {
	if ( typeof value !== 'string' ) {
		return null;
	}

	const normalized = value.trim().replaceAll( '_', '-' ).toLowerCase();
	if (
		normalized.length > MAX_LOCALE_LENGTH ||
		! LOCALE_PATTERN.test( normalized )
	) {
		return null;
	}

	return normalized;
};

const normalizeLocaleSetting = ( value: unknown ): string => {
	if ( value === 'site' || value === 'auto' ) {
		return value;
	}

	return normalizeLocaleToken( value ) ?? 'site';
};

const normalizeDirection = ( value: unknown ): DisplayDirection =>
	value === 'ltr' || value === 'rtl' || value === 'auto' ? value : 'auto';

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

	if (
		schedule.days.length === 0 &&
		schedule.start === null &&
		schedule.end === null
	) {
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
	const start =
		schedule.start === null ? null : timeToMinute( schedule.start );
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

const selectStarters = (
	config: DisplayRulesConfig,
	path: string
): readonly string[] => {
	const exact = config.starters.by_page.find(
		( rule ) => ! rule.pattern.includes( '*' ) && rule.pattern === path
	);
	if ( exact !== undefined ) {
		return exact.prompts;
	}

	let best: StarterRule | null = null;
	let bestSpecificity = -1;
	for ( const rule of config.starters.by_page ) {
		if (
			! rule.pattern.includes( '*' ) ||
			! pathMatchesPattern( path, rule.pattern )
		) {
			continue;
		}

		const specificity = rule.pattern.replaceAll( '*', '' ).length;
		if ( specificity > bestSpecificity ) {
			best = rule;
			bestSpecificity = specificity;
		}
	}

	return best?.prompts ?? config.starters.default;
};

const resolveLocale = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts
): string => {
	if ( config.localization.locale === 'auto' ) {
		return (
			normalizeLocaleToken( facts.documentLocale ) ??
			normalizeLocaleToken( facts.siteLocale ) ??
			'site'
		);
	}

	if ( config.localization.locale === 'site' ) {
		return normalizeLocaleToken( facts.siteLocale ) ?? 'site';
	}

	return config.localization.locale;
};

const resolveDirection = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts,
	locale: string
): 'ltr' | 'rtl' => {
	if (
		config.localization.direction === 'ltr' ||
		config.localization.direction === 'rtl'
	) {
		return config.localization.direction;
	}

	const language = locale.split( '-', 1 )[ 0 ];
	if (
		RTL_LOCALES.includes( language as ( typeof RTL_LOCALES )[ number ] )
	) {
		return 'rtl';
	}

	if (
		config.localization.locale === 'site' &&
		facts.siteDirection !== undefined
	) {
		return facts.siteDirection;
	}

	return 'ltr';
};

const baseDecision = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts,
	visible: boolean,
	reasons: readonly string[]
): DisplayDecision => {
	const path = normalizeFactPath( facts.path );
	const locale = resolveLocale( config, facts );

	return {
		visible,
		proactiveEligible: false,
		starters: selectStarters( config, path ),
		locale,
		direction: resolveDirection( config, facts, locale ),
		reasons,
	};
};

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
	const starters = asRecord( candidate.starters );
	const localization = asRecord( candidate.localization );
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
			default: normalizePromptList( starters.default ),
			by_page: normalizeStarterRules( starters.by_page ),
		},
		localization: {
			locale: normalizeLocaleSetting( localization.locale ),
			direction: normalizeDirection( localization.direction ),
		},
	};
};

export const evaluateDisplayRules = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts = {}
): DisplayDecision => {
	if ( ! config.enabled ) {
		return baseDecision( config, facts, false, [ 'disabled' ] );
	}

	const path = normalizeFactPath( facts.path );
	if (
		config.visibility.url_exclude.some( ( pattern ) =>
			pathMatchesPattern( path, pattern )
		)
	) {
		return baseDecision( config, facts, false, [ 'url_excluded' ] );
	}

	const reasons = [ 'enabled' ];
	if ( config.visibility.url_include.length > 0 ) {
		const included = config.visibility.url_include.some( ( pattern ) =>
			pathMatchesPattern( path, pattern )
		);
		if ( ! included ) {
			return baseDecision( config, facts, false, [ 'url_not_included' ] );
		}
		reasons.push( 'url_included' );
	}

	if ( ! audienceMatches( config, facts ) ) {
		return baseDecision( config, facts, false, [ 'audience_mismatch' ] );
	}

	if (
		config.visibility.post_types.length > 0 &&
		( typeof facts.postType !== 'string' ||
			! config.visibility.post_types.includes( facts.postType ) )
	) {
		return baseDecision( config, facts, false, [ 'post_type_mismatch' ] );
	}

	if (
		config.visibility.woo_areas.length > 0 &&
		( facts.wooArea === undefined ||
			! config.visibility.woo_areas.includes( facts.wooArea ) )
	) {
		return baseDecision( config, facts, false, [ 'woo_area_mismatch' ] );
	}

	if (
		config.visibility.devices.length > 0 &&
		( facts.device === undefined ||
			! config.visibility.devices.includes( facts.device ) )
	) {
		return baseDecision( config, facts, false, [ 'device_mismatch' ] );
	}

	if ( ! scheduleMatches( config, facts ) ) {
		return baseDecision( config, facts, false, [ 'schedule_mismatch' ] );
	}

	return baseDecision( config, facts, true, reasons );
};
