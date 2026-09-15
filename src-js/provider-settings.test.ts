import { ProviderSettingsScreen } from './provider-settings';

type ProviderCredentialState = {
	configured: boolean;
	source: string;
};

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
			const eventName = key.slice( 2 ).toLowerCase();
			element.addEventListener( eventName, value as EventListener );
			continue;
		}

		if ( value === undefined ) {
			continue;
		}

		if ( key === 'className' ) {
			element.className = String( value );
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

const installElementFactory = (): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );
};

const renderProviderSettings = (
	credential: ProviderCredentialState,
	providerId = 'openai_direct'
): HTMLElement => {
	installElementFactory();

	const root = document.createElement( 'div' );
	root.append(
		ProviderSettingsScreen( {
			providerId,
			credential,
		} ) as Node
	);

	return root;
};

describe( 'ProviderSettingsScreen', () => {
	it( 'renders configured option credential state without rehydrating any secret value', () => {
		const root = renderProviderSettings( {
			configured: true,
			source: 'option',
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

	it( 'renders a simple provider-specific configuration panel with WordPress controls', () => {
		const root = renderProviderSettings(
			{ configured: false, source: 'none' },
			'gemini_direct'
		);
		const panel = root.querySelector( '[data-provider-settings="gemini_direct"]' );
		const input = root.querySelector< HTMLInputElement >(
			'input[name="credential"]'
		);
		const submit = root.querySelector< HTMLButtonElement >(
			'form[data-provider-credential-form] button[type="submit"]'
		);
		const backLink = root.querySelector< HTMLAnchorElement >(
			'a[href="#/providers"]'
		);

		expect( panel?.querySelector( 'h2' )?.textContent ).toBe( 'Google Gemini' );
		expect( panel?.textContent ).toContain(
			'Add an API key, then choose a model.'
		);
		expect( backLink?.textContent ).toContain( 'Providers' );
		expect( input?.classList.contains( 'regular-text' ) ).toBe( true );
		expect( submit?.classList.contains( 'button' ) ).toBe( true );
		expect( submit?.classList.contains( 'button-primary' ) ).toBe( true );
	} );

	it( 'submits only the newly entered replacement credential and clears it after success', async () => {
		installElementFactory();
		const onReplace = jest.fn().mockResolvedValue( undefined );
		const root = document.createElement( 'div' );
		const render = ProviderSettingsScreen as unknown as ( props: {
			providerId: string;
			credential: ProviderCredentialState;
			onReplace: ( credential: string ) => Promise< void >;
		} ) => Node;
		root.append(
			render( {
				providerId: 'openai_direct',
				credential: { configured: true, source: 'option' },
				onReplace,
			} )
		);
		const form = root.querySelector< HTMLFormElement >(
			'form[data-provider-credential-form]'
		);
		const credentialInput = root.querySelector< HTMLInputElement >(
			'input[name="credential"]'
		);

		expect( form ).not.toBeNull();
		expect( credentialInput ).not.toBeNull();
		if ( form === null || credentialInput === null ) {
			return;
		}

		credentialInput.value = 'sk-new-only';
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await Promise.resolve();
		await Promise.resolve();

		expect( onReplace ).toHaveBeenCalledTimes( 1 );
		expect( onReplace ).toHaveBeenCalledWith( 'sk-new-only' );
		expect( credentialInput.value ).toBe( '' );
		expect( root.innerHTML ).not.toContain( 'sk-new-only' );
	} );
} );
