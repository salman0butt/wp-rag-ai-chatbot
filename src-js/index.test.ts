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
