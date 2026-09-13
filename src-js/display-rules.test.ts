import { evaluateDisplayRules, normalizeDisplayRules } from './display-rules';

type PathFacts = {
	path: string;
};

type PathEvaluator = (
	config: ReturnType< typeof normalizeDisplayRules >,
	facts: PathFacts
) => ReturnType< typeof evaluateDisplayRules >;

type AudienceFacts = {
	isAuthenticated: boolean;
	roleMatches?: readonly string[];
	postType?: string;
	wooArea?: 'shop' | 'product' | 'cart' | 'checkout' | 'account';
	device?: 'desktop' | 'tablet' | 'mobile';
};

type AudienceEvaluator = (
	config: ReturnType< typeof normalizeDisplayRules >,
	facts: AudienceFacts
) => ReturnType< typeof evaluateDisplayRules >;

const evaluateForPath = evaluateDisplayRules as unknown as PathEvaluator;
const evaluateForAudience =
	evaluateDisplayRules as unknown as AudienceEvaluator;

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
		expect( evaluateForPath( rules, { path: '/docs/guide' } ) ).toEqual( {
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

describe( 'display rule audience, post, WooCommerce, and device gates', () => {
	const rules = normalizeDisplayRules( {
		visibility: {
			audience: 'selected_roles',
			roles: [ 'editor', 'shop_manager' ],
			post_types: [ 'post', 'product' ],
			woo_areas: [ 'shop', 'product' ],
			devices: [ 'desktop', 'tablet' ],
		},
	} );

	test( 'normalizes finite configured gate values', () => {
		const normalized = rules as unknown as {
			visibility: Record< string, unknown >;
		};

		expect( normalized.visibility ).toEqual(
			expect.objectContaining( {
				audience: 'selected_roles',
				roles: [ 'editor', 'shop_manager' ],
				post_types: [ 'post', 'product' ],
				woo_areas: [ 'shop', 'product' ],
				devices: [ 'desktop', 'tablet' ],
			} )
		);
	} );

	test( 'uses OR within categories and AND across configured categories', () => {
		expect(
			evaluateForAudience( rules, {
				isAuthenticated: true,
				roleMatches: [ 'shop_manager' ],
				postType: 'product',
				wooArea: 'product',
				device: 'tablet',
			} )
		).toEqual( expect.objectContaining( { visible: true } ) );

		expect(
			evaluateForAudience( rules, {
				isAuthenticated: true,
				roleMatches: [ 'subscriber' ],
				postType: 'product',
				wooArea: 'product',
				device: 'tablet',
			} )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'audience_mismatch' ],
			} )
		);

		expect(
			evaluateForAudience( rules, {
				isAuthenticated: true,
				roleMatches: [ 'editor' ],
				postType: 'page',
				wooArea: 'product',
				device: 'tablet',
			} )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'post_type_mismatch' ],
			} )
		);

		expect(
			evaluateForAudience( rules, {
				isAuthenticated: true,
				roleMatches: [ 'editor' ],
				postType: 'product',
				wooArea: 'checkout',
				device: 'tablet',
			} )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'woo_area_mismatch' ],
			} )
		);

		expect(
			evaluateForAudience( rules, {
				isAuthenticated: true,
				roleMatches: [ 'editor' ],
				postType: 'product',
				wooArea: 'product',
				device: 'mobile',
			} )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'device_mismatch' ],
			} )
		);
	} );

	test( 'supports authenticated and anonymous audience modes without role data', () => {
		const authenticated = normalizeDisplayRules( {
			visibility: { audience: 'authenticated' },
		} );
		const anonymous = normalizeDisplayRules( {
			visibility: { audience: 'anonymous' },
		} );

		expect(
			evaluateForAudience( authenticated, { isAuthenticated: false } )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'audience_mismatch' ],
			} )
		);
		expect(
			evaluateForAudience( anonymous, { isAuthenticated: true } )
		).toEqual(
			expect.objectContaining( {
				visible: false,
				reasons: [ 'audience_mismatch' ],
			} )
		);
	} );
} );
