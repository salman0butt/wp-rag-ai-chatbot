import { evaluateDisplayRules, normalizeDisplayRules } from './display-rules';

describe( 'display rule defaults and disabled behavior', () => {
	test( 'normalizes missing config to M14-compatible presentation defaults', () => {
		expect( normalizeDisplayRules( undefined ) ).toEqual( {
			enabled: true,
			visibility: {
				url_include: [],
				url_exclude: [],
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
		} );
	} );

	test( 'returns a bounded visible decision for defaults and a disabled decision when disabled', () => {
		expect(
			evaluateDisplayRules( normalizeDisplayRules( undefined ) )
		).toEqual( {
			visible: true,
			proactiveEligible: false,
			starters: [],
			locale: 'site',
			direction: 'ltr',
			reasons: [ 'enabled' ],
		} );

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

describe( 'display rule URL precedence', () => {
	const rules = normalizeDisplayRules( {
		visibility: {
			url_include: [ ' /docs/* ', '/pricing' ],
			url_exclude: [ '/docs/private/*' ],
		},
	} );

	test( 'normalizes bounded include and exclude path patterns', () => {
		expect( rules.visibility ).toEqual( {
			url_include: [ '/docs/*', '/pricing' ],
			url_exclude: [ '/docs/private/*' ],
		} );
	} );

	test( 'allows include matches and lets exclusions win', () => {
		expect(
			evaluateDisplayRules( rules, { path: '/docs/guide' } )
		).toEqual( {
			visible: true,
			proactiveEligible: false,
			starters: [],
			locale: 'site',
			direction: 'ltr',
			reasons: [ 'enabled', 'url_included' ],
		} );

		expect(
			evaluateDisplayRules( rules, { path: '/docs/private/secret' } )
		).toEqual( {
			visible: false,
			proactiveEligible: false,
			starters: [],
			locale: 'site',
			direction: 'ltr',
			reasons: [ 'url_excluded' ],
		} );
	} );

	test( 'hides paths outside a non-empty include list', () => {
		expect( evaluateDisplayRules( rules, { path: '/contact' } ) ).toEqual( {
			visible: false,
			proactiveEligible: false,
			starters: [],
			locale: 'site',
			direction: 'ltr',
			reasons: [ 'url_not_included' ],
		} );
	} );
} );
