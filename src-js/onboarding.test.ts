import * as plugin from './index';

type OnboardingStep = 'provider' | 'model' | 'first_bot' | 'complete';
type OnboardingFlowComponent = ( props: { nextStep: OnboardingStep } ) => Node;
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

const renderOnboarding = ( nextStep: OnboardingStep ): HTMLElement => {
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
	root.append( ( OnboardingFlow as OnboardingFlowComponent )( { nextStep } ) );

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
} );
