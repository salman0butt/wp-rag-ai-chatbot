import * as plugin from './index';

type AdminShellComponent = ( props: {
	state: 'loading' | 'empty' | 'error' | 'ready';
	screen?: 'onboarding' | 'bots' | 'providers' | 'knowledge';
} ) => Node;

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( key === 'key' || value === undefined ) {
			continue;
		}

		if ( typeof value === 'boolean' ) {
			if ( value ) {
				element.setAttribute( key, '' );
			}
			continue;
		}

		element.setAttribute(
			key === 'htmlFor' ? 'for' : key,
			String( value )
		);
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const configureTestRuntime = (): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );
};

const renderKnowledgeState = ( state: 'loading' | 'error' ): HTMLElement => {
	configureTestRuntime();
	const exports = plugin as unknown as Record< string, unknown >;
	const AdminShell = exports.AdminShell as AdminShellComponent;
	const root = document.createElement( 'div' );

	root.append( AdminShell( { state, screen: 'knowledge' } ) );

	return root;
};

describe( 'Knowledge admin states', () => {
	it( 'renders a Knowledge-specific live loading state', () => {
		const root = renderKnowledgeState( 'loading' );
		const state = root.querySelector( '[data-knowledge-state="loading"]' );

		expect( state?.getAttribute( 'role' ) ).toBe( 'status' );
		expect( state?.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( state?.textContent ).toContain( 'Loading knowledge data' );
	} );

	it( 'renders a Knowledge-specific safe error state', () => {
		const root = renderKnowledgeState( 'error' );
		const state = root.querySelector( '[data-knowledge-state="error"]' );

		expect( state?.getAttribute( 'role' ) ).toBe( 'alert' );
		expect( state?.textContent ).toContain(
			'Knowledge data could not be loaded.'
		);
	} );
} );
