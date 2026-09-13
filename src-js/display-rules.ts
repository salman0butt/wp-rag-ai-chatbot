export type DisplayDirection = 'auto' | 'ltr' | 'rtl';

export type DisplayRulesConfig = {
	enabled: boolean;
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

export type DisplayDecision = {
	visible: boolean;
	proactiveEligible: boolean;
	starters: readonly string[];
	locale: string;
	direction: 'ltr' | 'rtl';
	reasons: readonly string[];
};

export const normalizeDisplayRules = ( value: unknown ): DisplayRulesConfig => {
	const candidate =
		typeof value === 'object' && value !== null
			? ( value as Record< string, unknown > )
			: {};

	return {
		enabled: candidate.enabled !== false,
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
	config: DisplayRulesConfig
): DisplayDecision => ( {
	visible: config.enabled,
	proactiveEligible: false,
	starters: [],
	locale: config.localization.locale,
	direction: config.localization.direction === 'rtl' ? 'rtl' : 'ltr',
	reasons: [ config.enabled ? 'enabled' : 'disabled' ],
} );
