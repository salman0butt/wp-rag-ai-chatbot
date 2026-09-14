import { evaluateDisplayRules, normalizeDisplayRules } from './display-rules';

type LocalizationFacts = {
	path?: string;
	siteLocale?: string;
	documentLocale?: string;
	siteDirection?: 'ltr' | 'rtl';
};

type LocalizationEvaluator = (
	config: ReturnType< typeof normalizeDisplayRules >,
	facts: LocalizationFacts
) => ReturnType< typeof evaluateDisplayRules >;

const evaluateWithLocalization =
	evaluateDisplayRules as unknown as LocalizationEvaluator;

describe( 'display rule starters and localization decisions', () => {
	test( 'normalizes bounded plain-text starter prompts and mappings', () => {
		const rules = normalizeDisplayRules( {
			starters: {
				default: [
					' General help ',
					'',
					'x'.repeat( 161 ),
					'Pricing',
					'Contact',
					'Fourth',
					'Fifth',
				],
				by_page: [
					{ pattern: ' /docs/* ', prompts: [ ' Glob first ' ] },
					{ pattern: '/docs/*', prompts: [ 'Glob second' ] },
					{ pattern: '/docs/exact', prompts: [ ' Exact match ' ] },
				],
			},
		} ) as unknown as {
			starters: Record< string, unknown >;
		};

		expect( rules.starters ).toEqual( {
			default: [ 'General help', 'Pricing', 'Contact', 'Fourth' ],
			by_page: [
				{ pattern: '/docs/*', prompts: [ 'Glob first' ] },
				{ pattern: '/docs/*', prompts: [ 'Glob second' ] },
				{ pattern: '/docs/exact', prompts: [ 'Exact match' ] },
			],
		} );
	} );

	test( 'prefers exact starter matches and first equal-specificity glob rule', () => {
		const rules = normalizeDisplayRules( {
			starters: {
				default: [ 'General' ],
				by_page: [
					{ pattern: '/docs/*', prompts: [ 'First glob' ] },
					{ pattern: '/docs/*', prompts: [ 'Second glob' ] },
					{ pattern: '/docs/exact', prompts: [ 'Exact' ] },
				],
			},
		} );

		expect(
			evaluateWithLocalization( rules, { path: '/docs/exact' } ).starters
		).toEqual( [ 'Exact' ] );
		expect(
			evaluateWithLocalization( rules, { path: '/docs/guide' } ).starters
		).toEqual( [ 'First glob' ] );
		expect(
			evaluateWithLocalization( rules, { path: '/other' } ).starters
		).toEqual( [ 'General' ] );
	} );

	test( 'uses explicit direction overrides and auto RTL locale inference', () => {
		const autoRtl = normalizeDisplayRules( {
			localization: { locale: 'ur', direction: 'auto' },
		} );
		const forcedLtr = normalizeDisplayRules( {
			localization: { locale: 'ur', direction: 'ltr' },
		} );
		const autoLocale = normalizeDisplayRules( {
			localization: { locale: 'auto', direction: 'auto' },
		} );

		expect( evaluateWithLocalization( autoRtl, {} ) ).toEqual(
			expect.objectContaining( { locale: 'ur', direction: 'rtl' } )
		);
		expect( evaluateWithLocalization( forcedLtr, {} ) ).toEqual(
			expect.objectContaining( { locale: 'ur', direction: 'ltr' } )
		);
		expect(
			evaluateWithLocalization( autoLocale, {
				siteLocale: 'en-US',
				documentLocale: 'ur-PK',
				siteDirection: 'ltr',
			} )
		).toEqual(
			expect.objectContaining( { locale: 'ur-pk', direction: 'rtl' } )
		);
	} );
} );
