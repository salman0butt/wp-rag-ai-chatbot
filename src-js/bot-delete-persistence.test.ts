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

		if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
			element.addEventListener(
				key.slice( 2 ).toLowerCase(),
				value as EventListener
			);
			continue;
		}

		if ( key === 'defaultValue' && element instanceof HTMLInputElement ) {
			element.defaultValue = String( value );
			element.value = String( value );
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

const tick = async (): Promise< void > => {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
};

describe( 'persisted bot deletion', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		jest.restoreAllMocks();
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'requires confirmation before deleting and refreshes server-authoritative state after confirmation', async () => {
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
		Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
			configurable: true,
			value: {
				plugin: 'wp-rag-ai-chatbot',
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				nonce: 'rest-nonce',
			},
		} );

		const existingBot = {
			id: 'bot-existing',
			name: 'Existing Bot',
			enabled: true,
			provider_id: 'openai',
			model_id: 'gpt-5-mini',
			version: 7,
			created_at: '2026-09-08T01:00:00+00:00',
			updated_at: '2026-09-08T01:00:00+00:00',
		};
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
					items: [ existingBot ],
					total: 1,
					page: 1,
					per_page: 20,
				} ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { deleted: true } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( {
					items: [],
					total: 0,
					page: 1,
					per_page: 20,
				} ),
			} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );
		const confirm = jest
			.spyOn( window, 'confirm' )
			.mockReturnValueOnce( false )
			.mockReturnValueOnce( true );

		expect( bootstrapAdminApp( '#/bots' ) ).toBe( true );
		await tick();
		await tick();

		const deleteButton = root.querySelector< HTMLButtonElement >(
			'button[data-delete-bot-id="bot-existing"]'
		);
		expect( deleteButton ).not.toBeNull();

		deleteButton!.click();
		await tick();
		expect( confirm ).toHaveBeenCalledTimes( 1 );
		expect( fetcher ).toHaveBeenCalledTimes( 2 );

		deleteButton!.click();
		await tick();
		await tick();

		expect( confirm ).toHaveBeenCalledTimes( 2 );
		expect( fetcher ).toHaveBeenNthCalledWith(
			3,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots/bot-existing',
			expect.objectContaining( {
				method: 'DELETE',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( fetcher ).toHaveBeenNthCalledWith(
			4,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots?page=1&per_page=20',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( root.querySelector( '[data-bot-list-empty]' ) ).not.toBeNull();
		expect(
			root.querySelector( '[data-bot-id="bot-existing"]' )
		).toBeNull();
	} );
} );
