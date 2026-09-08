import * as plugin from './index';

type OnboardingStep = 'provider' | 'model' | 'first_bot' | 'complete';
type OnboardingIssue =
	| 'provider_unavailable'
	| 'missing_credential'
	| 'unsupported_capability';
type OnboardingFlowComponent = ( props: {
	nextStep: OnboardingStep;
	issue?: OnboardingIssue;
} ) => Node;
type TestElementProps = Record< string, string > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		element.setAttribute( key, value );
	}

	for ( const child of children ) {
		element.append( child );
	}

	return element;
};

const renderOnboarding = (
	nextStep: OnboardingStep,
	issue?: OnboardingIssue
): HTMLElement => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );

	const exports = plugin as unknown as Record< string, unknown >;
	const OnboardingFlow = exports.OnboardingFlow;

	expect( typeof OnboardingFlow ).toBe( 'function' );

	const root = document.createElement( 'div' );
	root.append(
		( OnboardingFlow as OnboardingFlowComponent )( { nextStep, issue } )
	);

	return root;
};

describe( 'OnboardingFlow', () => {
	it.each( [
		[ 'provider', 'Connect a provider' ],
		[ 'model', 'Choose a model' ],
		[ 'first_bot', 'Create your first bot' ],
	] as const )(
		'guides persisted %s readiness to the expected first-run step',
		( nextStep, heading ) => {
			const root = renderOnboarding( nextStep );

			expect( root.querySelector( 'h2' )?.textContent ).toBe( heading );
			expect( root.getAttribute( 'data-onboarding-step' ) ).toBeNull();
			expect(
				root.firstElementChild?.getAttribute( 'data-onboarding-step' )
			).toBe( nextStep );
		}
	);

	it.each( [
		[
			'provider_unavailable',
			'The provider is currently unavailable.',
			'Review provider settings',
		],
		[
			'missing_credential',
			'Add a provider credential to continue.',
			'Configure provider',
		],
		[
			'unsupported_capability',
			'Choose a provider and model that support generation.',
			'Review compatible models',
		],
	] as const )(
		'renders an actionable accessible %s state',
		( issue, message, action ) => {
			const root = renderOnboarding( 'model', issue );
			const alert = root.querySelector( '[role="alert"]' );
			const link = alert?.querySelector( 'a' );

			expect( alert?.textContent ).toContain( message );
			expect( link?.textContent ).toBe( action );
			expect( link?.getAttribute( 'href' ) ).toBe( '#/providers' );
			expect( link?.getAttribute( 'autofocus' ) ).toBe( 'true' );
		}
	);
} );
