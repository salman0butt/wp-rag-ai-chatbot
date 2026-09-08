import * as plugin from './index';

type ProviderCredentialState = {
	configured: boolean;
	source: string;
};

type ProviderSettingsComponent = ( props: {
	providerId: string;
	credential: ProviderCredentialState;
} ) => Node;

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( key.startsWith( 'on' ) || value === undefined ) {
			continue;
		}

		if ( key === 'htmlFor' ) {
			element.setAttribute( 'for', String( value ) );
			continue;
		}

		element.setAttribute( key, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const renderProviderSettings = (
	credential: ProviderCredentialState
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
	const ProviderSettingsScreen = exports.ProviderSettingsScreen;

	expect( typeof ProviderSettingsScreen ).toBe( 'function' );

	const root = document.createElement( 'div' );
	root.append(
		( ProviderSettingsScreen as ProviderSettingsComponent )( {
			providerId: 'openai-direct',
			credential,
		} )
	);

	return root;
};

describe( 'ProviderSettingsScreen', () => {
	it( 'renders configured credential state without rehydrating any secret value', () => {
		const root = renderProviderSettings( {
			configured: true,
			source: 'managed',
		} );
		const credentialInput = root.querySelector< HTMLInputElement >(
			'input[name="credential"]'
		);

		expect( root.textContent ).toContain( 'Credential configured' );
		expect( root.textContent ).toContain( 'Managed by this plugin' );
		expect( credentialInput ).not.toBeNull();
		expect( credentialInput?.type ).toBe( 'password' );
		expect( credentialInput?.value ).toBe( '' );
		expect( credentialInput?.getAttribute( 'value' ) ).toBeNull();
		expect( root.innerHTML ).not.toContain( 'sk-' );
	} );
} );
