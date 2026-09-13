export type DisplayRulesConfig = {
	enabled: boolean;
};

export type DisplayDecision = {
	visible: boolean;
	reasons: readonly string[];
};

export const normalizeDisplayRules = ( value: unknown ): DisplayRulesConfig => {
	if ( typeof value !== 'object' || value === null ) {
		return { enabled: true };
	}

	const candidate = value as Record< string, unknown >;

	return {
		enabled: candidate.enabled !== false,
	};
};

export const evaluateDisplayRules = (
	config: DisplayRulesConfig
): DisplayDecision =>
	config.enabled
		? { visible: true, reasons: [] }
		: { visible: false, reasons: [ 'disabled' ] };
