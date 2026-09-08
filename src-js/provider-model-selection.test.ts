import { ProviderSettingsScreen } from './provider-settings';

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
		element.setAttribute( key === 'htmlFor' ? 'for' : key, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

describe( 'provider model selection', () => {
	beforeEach( () => {
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: { element: { createElement: createTestElement } },
		} );
	} );

	it( 'renders only models supplied by the server-authoritative compatibility resource', () => {
		const screen = ProviderSettingsScreen( {
			providerId: 'openai_direct',
			credential: { configured: true, source: 'managed' },
			models: [
				{ model_id: 'gpt-generation', display_name: 'GPT Generation' },
			],
		} as Parameters< typeof ProviderSettingsScreen >[ 0 ] & {
			models: Array< { model_id: string; display_name: string } >;
		} ) as HTMLElement;

		const selector = screen.querySelector< HTMLSelectElement >(
			'select[name="model_id"]'
		);

		expect( selector ).not.toBeNull();
		expect(
			Array.from( selector?.options ?? [] ).map( ( option ) => option.value )
		).toEqual( [ 'gpt-generation' ] );
		expect( screen.textContent ).toContain( 'GPT Generation' );
		expect( screen.textContent ).not.toContain( 'embedding-only' );
	} );
} );
