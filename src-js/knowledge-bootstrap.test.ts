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

const sourcePageResponse = ( title: string ) => ( {
	ok: true,
	status: 200,
	json: async () => ( {
		items: [
			{
				id: 'source-alpha',
				source_key: 'source-alpha',
				source_type: 'wordpress_posts',
				external_id: null,
				title,
				canonical_url: 'https://example.test/support',
				status: 'indexed',
				last_synced_at: null,
				updated_at: '2026-09-08T19:05:00+00:00',
			},
		],
		total: 21,
		page: 2,
		per_page: 20,
	} ),
} );

describe( 'knowledge admin bootstrap', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'loads the bounded source page from the hash through the nonce-authenticated admin client', async () => {
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { ready: true, next_step: 'complete' } ),
			} )
			.mockResolvedValueOnce( sourcePageResponse( 'Support Articles' ) );
		const root = configureAdminRuntime( fetcher );

		window.location.hash = '#/knowledge/source-alpha?page=2';
		expect( bootstrapAdminApp() ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenNthCalledWith(
			2,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/sources?page=2&per_page=20',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect(
			root.querySelector( '[data-knowledge-source-id="source-alpha"]' )
				?.textContent
		).toBe( 'Support Articles' );
		expect(
			root.querySelector(
				'nav[aria-label="Administration"] a[aria-current="page"]'
			)?.textContent
		).toBe( 'Knowledge' );
	} );

	it( 'refetches the same bounded page after navigating away and back to Knowledge', async () => {
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { ready: true, next_step: 'complete' } ),
			} )
			.mockResolvedValueOnce( sourcePageResponse( 'Support Articles' ) )
			.mockResolvedValueOnce( sourcePageResponse( 'Updated Articles' ) );
		const root = configureAdminRuntime( fetcher );

		window.location.hash = '#/knowledge/source-alpha?page=2';
		expect( bootstrapAdminApp() ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		window.location.hash = '#/onboarding';
		window.dispatchEvent( new Event( 'hashchange' ) );
		window.location.hash = '#/knowledge/source-alpha?page=2';
		window.dispatchEvent( new Event( 'hashchange' ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenCalledTimes( 3 );
		expect( fetcher ).toHaveBeenNthCalledWith(
			3,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/sources?page=2&per_page=20',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect(
			root.querySelector( '[data-knowledge-source-id="source-alpha"]' )
				?.textContent
		).toBe( 'Updated Articles' );
	} );
} );
