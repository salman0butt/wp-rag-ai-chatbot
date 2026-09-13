import { evaluateDisplayRules, normalizeDisplayRules } from './display-rules';

type PathFacts = {
	path: string;
};

type PathEvaluator = (
	config: ReturnType< typeof normalizeDisplayRules >,
	facts: PathFacts
) => ReturnType< typeof evaluateDisplayRules >;

const evaluateForPath = evaluateDisplayRules as unknown as PathEvaluator;

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
		const normalized = rules as unknown as Record< string, unknown >;

		expect( normalized.visibility ).toEqual( {
			url_include: [ '/docs/*', '/pricing' ],
			url_exclude: [ '/docs/private/*' ],
		} );
	} );

	test( 'allows include matches and lets exclusions win', () => {
		expect(
			evaluateForPath( rules, { path: '/docs/guide' } )
		).toEqual( {
			visible: true,
			proactiveEligible: false,
			starters: [],
			locale: 'site',
			direction: 'ltr',
			reasons: [ 'enabled', 'url_included' ],
		} );

		expect(
			evaluateForPath( rules, { path: '/docs/private/secret' } )
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
		expect( evaluateForPath( rules, { path: '/contact' } ) ).toEqual( {
			visible: false,
			proactiveEligible: false,
			starters: [],
			locale: 'site',
			direction: 'ltr',
			reasons: [ 'url_not_included' ],
		} );
	} );
} );
