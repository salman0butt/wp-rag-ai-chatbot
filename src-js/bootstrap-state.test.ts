import { bootstrapAdminApp } from './index';

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

const configureElementRuntime = (): HTMLElement => {
	const render = jest.fn( ( element: Node, root: Element ) => {
		root.replaceChildren( element );
	} );
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
				render,
			},
		},
	} );
	const root = document.createElement( 'div' );
	root.id = 'wp-rag-ai-chatbot-admin';
	document.body.append( root );

	return root;
};

const configureAdminRuntime = ( fetcher: jest.Mock ): HTMLElement => {
	const root = configureElementRuntime();
	Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
		configurable: true,
		value: {
			plugin: 'wp-rag-ai-chatbot',
			restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			nonce: 'rest-nonce',
		},
	} );
	Object.defineProperty( window, 'fetch', {
		configurable: true,
		value: fetcher,
	} );

	return root;
};

describe( 'bootstrapAdminApp server-derived state', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'renders loading before resuming the first-bot onboarding step from persisted readiness', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: true,
			status: 200,
			json: async () => ( { ready: false, next_step: 'first_bot' } ),
		} );
		const root = configureAdminRuntime( fetcher );

		expect( bootstrapAdminApp( '#/onboarding' ) ).toBe( true );
		expect( root.querySelector( '[role="status"]' )?.textContent ).toBe(
			'Loading administration data…'
		);

		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/onboarding/readiness',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		const heading = root.querySelector(
			'[data-onboarding-step="first_bot"] h2'
		);
		expect( heading?.textContent ).toBe( 'Create your first bot' );
	} );

	it( 'resumes the onboarding screen from the persisted server next step', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: true,
			status: 200,
			json: async () => ( { ready: false, next_step: 'model' } ),
		} );
		const root = configureAdminRuntime( fetcher );

		expect( bootstrapAdminApp( '#/onboarding' ) ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const heading = root.querySelector(
			'[data-onboarding-step="model"] h2'
		);
		expect( heading?.textContent ).toBe( 'Choose a model' );
	} );

	it( 'replaces loading with the safe error state when readiness fails', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: false,
			status: 503,
			json: async () => ( {
				code: 'provider_unavailable',
				message: 'provider-secret-upstream-detail',
			} ),
		} );
		const root = configureAdminRuntime( fetcher );

		expect( bootstrapAdminApp( '#/onboarding' ) ).toBe( true );
		expect( root.querySelector( '[role="status"]' )?.textContent ).toBe(
			'Loading administration data…'
		);

		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'Administration data could not be loaded.'
		);
		expect( root.textContent ).not.toContain(
			'provider-secret-upstream-detail'
		);
	} );

	it( 'rerenders the ready shell on hash navigation without refetching readiness', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: true,
			status: 200,
			json: async () => ( { ready: true, next_step: 'complete' } ),
		} );
		const root = configureAdminRuntime( fetcher );

		window.location.hash = '#/onboarding';
		expect( bootstrapAdminApp() ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( root.querySelector( 'main h1' )?.textContent ).toBe(
			'Onboarding'
		);

		window.location.hash = '#/providers';
		window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );

		expect( root.querySelector( 'main h1' )?.textContent ).toBe(
			'Providers'
		);
		expect( fetcher ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'fails closed when the browser fetch transport is unavailable', () => {
		const root = configureElementRuntime();
		Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
			configurable: true,
			value: {
				plugin: 'wp-rag-ai-chatbot',
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				nonce: 'rest-nonce',
			},
		} );
		Reflect.deleteProperty( window, 'fetch' );

		expect( bootstrapAdminApp( '#/onboarding' ) ).toBe( true );
		expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'Administration data could not be loaded.'
		);
		expect( root.querySelector( '[data-admin-state="ready"]' ) ).toBeNull();
	} );

	it( 'fails closed when the WordPress boot configuration is missing', () => {
		const root = configureElementRuntime();
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );

		expect( bootstrapAdminApp( '#/onboarding' ) ).toBe( true );
		expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'Administration data could not be loaded.'
		);
	} );
} );
