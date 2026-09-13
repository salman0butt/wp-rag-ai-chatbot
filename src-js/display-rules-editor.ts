import {
	evaluateDisplayRules,
	type DisplayDecision,
	type DisplayRuleFacts,
	type DisplayRulesConfig,
} from './display-rules';

export const previewDisplayRules = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts
): DisplayDecision => evaluateDisplayRules( config, facts );
