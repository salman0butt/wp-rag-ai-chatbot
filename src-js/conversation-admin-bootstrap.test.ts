import { bootstrapAdminApp } from './index';

type TestElement = {
	tagName: string;
	props: Record< string, unknown > | null;
	children: unknown[];
};

const createElement = (
	tagName: string,
	props: Record< string, unknown > | null,
	...children: unknown[]
): TestElement => ( { tagName, props, children } );

describe( 'conversation admin bootstrap', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'loads the bounded conversation list for the current route page', async () => {
		const render = jest.fn();
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: {
				element: {
					createElement,
					render,
				},
			},
		} );

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

		const fetcher = jest.fn( async ( requestUrl: string ) => ( {
			ok: true,
			status: 200,
			json: async () =>
				requestUrl.includes( '/admin/onboarding/readiness' )
					? { ready: true, next_step: 'complete' }
					: {
							items: [],
							page: 2,
							per_page: 25,
					  },
		} ) );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect( bootstrapAdminApp( '#/conversations?page=2' ) ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/conversations?page=2&per_page=25',
			expect.objectContaining( { method: 'GET' } )
		);
		expect( render ).toHaveBeenCalled();
	} );
} );
