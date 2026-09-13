import { evaluateDisplayRules, normalizeDisplayRules } from './display-rules';

describe( 'display rule defaults and disabled behavior', () => {
	test( 'normalizes missing config to M14-compatible presentation defaults', () => {
		expect( normalizeDisplayRules( undefined ) ).toEqual( {
			enabled: true,
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
		} );
	} );

	test( 'returns a bounded visible decision for defaults and a disabled decision when disabled', () => {
		expect( evaluateDisplayRules( normalizeDisplayRules( undefined ) ) ).toEqual(
			{
				visible: true,
				proactiveEligible: false,
				starters: [],
				locale: 'site',
				direction: 'ltr',
				reasons: [ 'enabled' ],
			}
		);

		expect(
			evaluateDisplayRules( normalizeDisplayRules( { enabled: false } ) )
		).toEqual( {
			visible: false,
			proactiveEligible: false,
			starters: [],
			locale: 'site',
			direction: 'ltr',
			reasons: [ 'disabled' ],
		} );
	} );
} );
