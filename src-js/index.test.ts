import { createElement, render } from '@wordpress/element';

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

type AdminShellComponent = ( props: {
	state: 'loading' | 'empty' | 'error' | 'ready';
} ) => ReturnType< typeof createElement >;

const renderAdminShell = (
	state: 'loading' | 'empty' | 'error' | 'ready'
): HTMLElement => {
	const exports = plugin as unknown as Record< string, unknown >;
	const AdminShell = exports.AdminShell;

	expect( typeof AdminShell ).toBe( 'function' );

	const root = document.createElement( 'div' );
	render(
		createElement( AdminShell as AdminShellComponent, { state } ),
		root
	);

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

		expect( root.querySelector( '[data-admin-state="empty"]' )?.textContent ).toContain(
			'No bots configured yet.'
		);
	} );

	it( 'announces a safe error without provider or server details', () => {
		const root = renderAdminShell( 'error' );

		expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'Administration data could not be loaded.'
		);
	} );
} );
