import { bootstrapAdminApp } from './index';

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

const configureAdminRuntime = ( fetcher: jest.Mock ): HTMLElement => {
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
	const root = document.createElement( 'div' );
	root.id = 'wp-rag-ai-chatbot-admin';
	document.body.append( root );

	return root;
};

const okJson = ( payload: unknown ) => ( {
	ok: true,
	status: 200,
	json: async () => payload,
} );

describe( 'knowledge document chunk bootstrap', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'loads and renders only the selected document bounded chunk page', async () => {
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce(
				okJson( { ready: true, next_step: 'complete' } )
			)
			.mockResolvedValueOnce(
				okJson( {
					items: [
						{
							id: 17,
							source_key: 'source-17',
							source_type: 'wordpress_posts',
							external_id: null,
							title: 'Support Source',
							canonical_url: null,
							status: 'indexed',
							last_synced_at: null,
							updated_at: '2026-09-08T19:05:00+00:00',
						},
					],
					total: 1,
					page: 2,
					per_page: 20,
				} )
			)
			.mockResolvedValueOnce(
				okJson( {
					id: 17,
					source_key: 'source-17',
					source_type: 'wordpress_posts',
					external_id: null,
					title: 'Support Source',
					canonical_url: null,
					status: 'indexed',
					last_synced_at: null,
					created_at: '2026-09-08T18:00:00+00:00',
					updated_at: '2026-09-08T19:05:00+00:00',
				} )
			)
			.mockResolvedValueOnce(
				okJson( {
					items: [
						{
							id: 31,
							document_key: 'doc-support',
							source_id: 17,
							external_id: null,
							document_type: 'post',
							title: 'Reset your password',
							canonical_url: null,
							source_version: '7',
							language: 'en',
							visibility: 'public',
							created_at: '2026-09-08T18:10:00+00:00',
							updated_at: '2026-09-08T19:00:00+00:00',
						},
					],
					total: 1,
					page: 1,
					per_page: 20,
				} )
			)
			.mockResolvedValueOnce(
				okJson( {
					items: [
						{
							content: 'Reset it from the account security page.',
							content_truncated: false,
							sequence: 0,
						},
					],
					total: 1,
					page: 1,
					per_page: 20,
				} )
			);
		const root = configureAdminRuntime( fetcher );

		window.location.hash = '#/knowledge/17/documents/doc-support?page=2';
		expect( bootstrapAdminApp() ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenNthCalledWith(
			5,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/sources/17/documents/doc-support/chunks?page=1&per_page=20',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect(
			root.querySelector( '[data-knowledge-chunk-sequence="0"]' )
				?.textContent
		).toContain( 'Reset it from the account security page.' );
	} );
} );
