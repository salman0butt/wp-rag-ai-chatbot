import {
	normalizeDisplayRules,
	type DisplayDecision,
	type DisplayRuleFacts,
	type DisplayRulesConfig,
} from './display-rules';

type DisplayRulesEditorModule = {
	previewDisplayRules: (
		config: DisplayRulesConfig,
		facts: DisplayRuleFacts
	) => DisplayDecision;
};

const loadEditorModule = (): DisplayRulesEditorModule =>
	jest.requireActual< DisplayRulesEditorModule >( './display-rules-editor' );

describe( 'display rules admin editor preview', () => {
	it( 'uses the production evaluator contract without persisting simulated visitor facts', () => {
		const config = normalizeDisplayRules( {
			visibility: {
				url_include: [ '/pricing' ],
				audience: 'authenticated',
			},
			starters: {
				default: [ 'Ask about pricing' ],
			},
		} );
		const simulatedFacts: DisplayRuleFacts = {
			path: '/pricing',
			isAuthenticated: true,
			device: 'desktop',
		};
		const before = JSON.stringify( config );

		const decision = loadEditorModule().previewDisplayRules(
			config,
			simulatedFacts
		);

		expect( decision.visible ).toBe( true );
		expect( decision.starters ).toEqual( [ 'Ask about pricing' ] );
		expect( JSON.stringify( config ) ).toBe( before );
		expect( config ).not.toHaveProperty( 'path' );
		expect( config ).not.toHaveProperty( 'isAuthenticated' );
		expect( config ).not.toHaveProperty( 'device' );
	} );
} );
