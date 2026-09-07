import * as plugin from './index';

type AdminClientFactory = ( config: {
	baseUrl: string;
	nonce: string;
	fetcher: typeof fetch;
} ) => {
	request: < T >(
		path: string,
		options?: { method?: string; body?: unknown }
	) => Promise< T >;
};

type AdminShellState = 'loading' | 'empty' | 'error' | 'ready';
type AdminScreen = 'onboarding' | 'bots' | 'providers';

type AdminShellComponent = ( props: {
	state: AdminShellState;
	screen?: AdminScreen;
} ) => Node;

type TestElementProps = Record< string, string > | null;
type TestRender = ( element: Node, root: Element ) => void;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( key === 'className' ) {
			element.className = value;
		} else {
			element.setAttribute( key, value );
		}
	}

	for ( const child of children ) {
		element.append( child );
	}

	return element;
};

const configureTestElementRuntime = ( render?: TestRender ): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
				...( render ? { render } : {} ),
			},
		},
	} );
};

const renderAdminShell = (
	state: AdminShellState,
	screen?: AdminScreen
): HTMLElement => {
	configureTestElementRuntime();

	const exports = plugin as unknown as Record< string, unknown >;
	const AdminShell = exports.AdminShell;

	expect( typeof AdminShell ).toBe( 'function' );

	const root = document.createElement( 'div' );
	root.append( ( AdminShell as AdminShellComponent )( { state, screen } ) );

	return root;
};

describe( 'pluginIdentity', () => {
	it( 'uses the canonical plugin slug and development version', () => {
		expect( plugin.pluginIdentity ).toEqual( {
			slug: 'wp-rag-ai-chatbot',
			version: '0.1.0-dev',
		} );
	} );
} );

describe( 'createAdminApiClient', () => {
	it( 'sends WordPress REST nonce and JSON headers without putting the nonce in the URL', async () => {
		const exports = plugin as unknown as Record< string, unknown >;
		const createAdminApiClient = exports.createAdminApiClient;

		expect( typeof createAdminApiClient ).toBe( 'function' );

		const fetcher = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( { ready: true } ),
		} );
		const client = ( createAdminApiClient as AdminClientFactory )( {
			baseUrl: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			nonce: 'rest-nonce',
			fetcher: fetcher as unknown as typeof fetch,
		} );

		await expect(
			client.request< { ready: boolean } >( '/admin/bootstrap' )
		).resolves.toEqual( { ready: true } );
		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bootstrap',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( fetcher.mock.calls[ 0 ][ 0 ] ).not.toContain( 'rest-nonce' );
	} );

	it( 'normalizes failed REST responses without exposing arbitrary response messages', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: false,
			status: 403,
			json: async () => ( {
				code: 'rest_forbidden',
				message: 'provider-secret-upstream-detail',
			} ),
		} );
		const client = plugin.createAdminApiClient( {
			baseUrl: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			nonce: 'rest-nonce',
			fetcher: fetcher as unknown as typeof fetch,
		} );
		let failure: unknown;

		try {
			await client.request( '/admin/bootstrap' );
		} catch ( error ) {
			failure = error;
		}

		expect( failure ).toMatchObject( {
			name: 'AdminApiError',
			code: 'rest_forbidden',
			status: 403,
			message: 'The admin request could not be completed.',
		} );
		expect( ( failure as Error ).message ).not.toContain(
			'provider-secret-upstream-detail'
		);
	} );
} );

describe( 'AdminShell', () => {
	it( 'announces loading state without exposing interactive content', () => {
		const root = renderAdminShell( 'loading' );

		expect( root.querySelector( '[role="status"]' )?.textContent ).toBe(
			'Loading administration data…'
		);
		expect( root.querySelector( 'nav' ) ).toBeNull();
	} );

	it( 'renders an explicit empty state', () => {
		const root = renderAdminShell( 'empty' );

		expect(
			root.querySelector( '[data-admin-state="empty"]' )?.textContent
		).toContain( 'No bots configured yet.' );
	} );

	it( 'announces a safe error without provider or server details', () => {
		const root = renderAdminShell( 'error' );

		expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'Administration data could not be loaded.'
		);
	} );

	it( 'renders accessible navigation and the selected ready screen', () => {
		const root = renderAdminShell( 'ready', 'bots' );
		const nav = root.querySelector( 'nav[aria-label="Administration"]' );
		const links = Array.from( nav?.querySelectorAll( 'a' ) ?? [] );

		expect( links.map( ( link ) => link.getAttribute( 'href' ) ) ).toEqual(
			[ '#/onboarding', '#/bots', '#/providers' ]
		);
		expect(
			nav?.querySelector( 'a[aria-current="page"]' )?.textContent
		).toBe( 'Bots' );
		expect( root.querySelector( 'main h1' )?.textContent ).toBe( 'Bots' );
	} );
} );

describe( 'resolveAdminScreen', () => {
	it( 'normalizes known hashes and falls back to onboarding', () => {
		const exports = plugin as unknown as Record< string, unknown >;
		const resolveAdminScreen = exports.resolveAdminScreen as
			| ( ( hash: string ) => AdminScreen )
			| undefined;

		expect( typeof resolveAdminScreen ).toBe( 'function' );
		expect( resolveAdminScreen?.( '#/bots' ) ).toBe( 'bots' );
		expect( resolveAdminScreen?.( '#/providers' ) ).toBe( 'providers' );
		expect( resolveAdminScreen?.( '#/unknown' ) ).toBe( 'onboarding' );
		expect( resolveAdminScreen?.( '' ) ).toBe( 'onboarding' );
	} );
} );

describe( 'bootstrapAdminApp', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
	} );

	it( 'does nothing when the admin mount boundary is absent', () => {
		const render = jest.fn();
		configureTestElementRuntime( render );
		const exports = plugin as unknown as Record< string, unknown >;
		const bootstrapAdminApp = exports.bootstrapAdminApp as
			| ( ( hash?: string ) => boolean )
			| undefined;

		expect( typeof bootstrapAdminApp ).toBe( 'function' );
		expect( bootstrapAdminApp?.( '#/bots' ) ).toBe( false );
		expect( render ).not.toHaveBeenCalled();
	} );

	it( 'mounts the selected ready screen from the safe WordPress boot payload', () => {
		const render = jest.fn( ( element: Node, root: Element ) => {
			root.append( element );
		} );
		configureTestElementRuntime( render );
		const root = document.createElement( 'div' );
		root.id = 'wp-rag-ai-chatbot-admin';
		document.body.append( root );
		Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
			configurable: true,
			value: {
				plugin: 'wp-rag-ai-chatbot',
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				nonce: 'rest-nonce',
			},
		} );
		const exports = plugin as unknown as Record< string, unknown >;
		const bootstrapAdminApp = exports.bootstrapAdminApp as
			| ( ( hash?: string ) => boolean )
			| undefined;

		expect( bootstrapAdminApp?.( '#/providers' ) ).toBe( true );
		expect( render ).toHaveBeenCalledTimes( 1 );
		expect( render.mock.calls[ 0 ][ 1 ] ).toBe( root );
		expect(
			root.querySelector( 'a[aria-current="page"]' )?.textContent
		).toBe( 'Providers' );
		expect( root.querySelector( 'main h1' )?.textContent ).toBe(
			'Providers'
		);
	} );
} );
