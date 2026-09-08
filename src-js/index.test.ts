import * as plugin from './index';

const createTestElement = (
	tagName: string,
	props: Record< string, unknown > | null,
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

type AdminShellState = 'loading' | 'empty' | 'error' | 'ready';
type AdminScreen = 'onboarding' | 'bots' | 'providers' | 'knowledge';

const renderAdminShell = (
	state: AdminShellState,
	screen?: AdminScreen
): HTMLElement => {
	const exports = plugin as unknown as Record< string, unknown >;
	const AdminShell = exports.AdminShell as
		| ( ( props: {
				state: AdminShellState;
				screen?: AdminScreen;
		  } ) => Node )
		| undefined;

	expect( typeof AdminShell ).toBe( 'function' );

	return AdminShell?.( { state, screen } ) as HTMLElement;
};

beforeEach( () => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
				render: jest.fn(),
			},
		},
	} );
} );

afterEach( () => {
	document.body.innerHTML = '';
	Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
} );

describe( 'plugin identity', () => {
	it( 'exports the expected plugin slug and development version', () => {
		expect( plugin.pluginIdentity ).toEqual( {
			slug: 'wp-rag-ai-chatbot',
			version: '0.1.0-dev',
		} );
	} );
} );

describe( 'createAdminApiClient', () => {
	it( 'uses the WordPress nonce and same-origin credentials for requests', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: true,
			status: 200,
			json: async () => ( { ok: true } ),
		} );
		const client = plugin.createAdminApiClient( {
			baseUrl: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1/',
			nonce: 'rest-nonce',
			fetcher,
		} );

		await client.request( '/admin/example' );

		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/example',
			expect.objectContaining( {
				credentials: 'same-origin',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
				method: 'GET',
			} )
		);
	} );

	it( 'maps server failures to a stable admin error without backend detail leakage', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: false,
			status: 500,
			json: async () => ( {
				code: 'provider_unavailable',
				message: 'secret upstream details',
			} ),
		} );
		const client = plugin.createAdminApiClient( {
			baseUrl: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			nonce: 'rest-nonce',
			fetcher,
		} );

		await expect( client.request( '/admin/example' ) ).rejects.toMatchObject( {
			name: 'AdminApiError',
			message: 'The admin request could not be completed.',
			code: 'provider_unavailable',
			status: 500,
		} );
	} );
} );

describe( 'AdminShell', () => {
	it( 'announces loading state accessibly', () => {
		const root = renderAdminShell( 'loading' );

		expect( root.getAttribute( 'role' ) ).toBe( 'status' );
		expect( root.getAttribute( 'aria-live' ) ).toBe( 'polite' );
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

		expect( links.map( ( link ) => link.getAttribute( 'href' ) ) ).toEqual( [
			'#/onboarding',
			'#/bots',
			'#/providers',
			'#/knowledge',
		] );
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
