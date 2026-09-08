import { BotManagementScreen, bootstrapAdminApp } from './index';

interface BotListItem {
	id: string;
	name: string;
	enabled: boolean;
	provider_id: string;
	model_id: string;
	version: number;
	created_at: string;
	updated_at: string;
}

interface BotPage {
	items: BotListItem[];
	total: number;
	page: number;
	per_page: number;
}

type BotManagementComponent = ( props: {
	page: BotPage;
	selectedBotId?: string;
} ) => Node;

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

		if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
			element.addEventListener(
				key.slice( 2 ).toLowerCase(),
				value as EventListener
			);
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

const configureRuntime = ( fetcher?: jest.Mock ): HTMLElement => {
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

	if ( fetcher !== undefined ) {
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
	}

	const root = document.createElement( 'div' );
	root.id = 'wp-rag-ai-chatbot-admin';
	document.body.append( root );

	return root;
};

const bot = ( id: string, name: string ): BotListItem => ( {
	id,
	name,
	enabled: true,
	provider_id: 'openai',
	model_id: 'gpt-5-mini',
	version: 1,
	created_at: '2026-09-08T00:00:00+00:00',
	updated_at: '2026-09-08T00:00:00+00:00',
} );

describe( 'Task 8 bot pagination navigation', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'renders selected-record context and previous/next hash links', () => {
		configureRuntime();
		const root = document.createElement( 'div' );
		root.append(
			( BotManagementScreen as BotManagementComponent )( {
				page: {
					items: [
						bot( 'bot-alpha', 'Support Bot' ),
						bot( 'bot-beta', 'Sales Bot' ),
					],
					total: 5,
					page: 2,
					per_page: 2,
				},
				selectedBotId: 'bot-beta',
			} )
		);

		expect(
			root.querySelector( 'a[href="#/bots/bot-beta?page=2"]' )
				?.getAttribute( 'aria-current' )
		).toBe( 'true' );
		expect(
			root.querySelector( 'a[data-bot-page="previous"]' )?.getAttribute( 'href' )
		).toBe( '#/bots?page=1' );
		expect(
			root.querySelector( 'a[data-bot-page="next"]' )?.getAttribute( 'href' )
		).toBe( '#/bots?page=3' );
	} );

	it( 'loads the page encoded in the bots hash through the Task 3 REST contract', async () => {
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { ready: true, next_step: 'complete' } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( {
					items: [ bot( 'bot-page-2', 'Page Two Bot' ) ],
					total: 21,
					page: 2,
					per_page: 20,
				} ),
			} );
		const root = configureRuntime( fetcher );

		expect( bootstrapAdminApp( '#/bots?page=2' ) ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenNthCalledWith(
			2,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots?page=2&per_page=20',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect(
			root.querySelector( '[data-bot-id="bot-page-2"]' )?.textContent
		).toBe( 'Page Two Bot' );
	} );
} );
