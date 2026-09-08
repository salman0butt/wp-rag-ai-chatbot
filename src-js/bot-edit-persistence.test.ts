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

describe( 'persisted bot editing', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'puts an edited bot with its optimistic version and refreshes server-authoritative state', async () => {
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
		const updatedBot = {
			...existingBot,
			name: 'Updated Bot',
			model_id: 'gpt-5.1-mini',
			version: 8,
			updated_at: '2026-09-08T02:00:00+00:00',
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
				json: async () => ( { bot: updatedBot } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( {
					items: [ updatedBot ],
					total: 1,
					page: 1,
					per_page: 20,
				} ),
			} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect( bootstrapAdminApp( '#/bots' ) ).toBe( true );
		await tick();
		await tick();

		const form = root.querySelector(
			'form[data-bot-editor="edit"][data-bot-id="bot-existing"]'
		);
		const name = form?.querySelector< HTMLInputElement >(
			'input[name="name"]'
		);
		const provider = form?.querySelector< HTMLInputElement >(
			'input[name="provider_id"]'
		);
		const model = form?.querySelector< HTMLInputElement >(
			'input[name="model_id"]'
		);

		expect( form ).not.toBeNull();
		expect( name?.value ).toBe( 'Existing Bot' );
		expect( provider?.value ).toBe( 'openai' );
		expect( model?.value ).toBe( 'gpt-5-mini' );

		name!.value = 'Updated Bot';
		model!.value = 'gpt-5.1-mini';
		form!.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			3,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots/bot-existing',
			expect.objectContaining( {
				method: 'PUT',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
				body: JSON.stringify( {
					version: 7,
					name: 'Updated Bot',
					provider_id: 'openai',
					model_id: 'gpt-5.1-mini',
					enabled: true,
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
		expect(
			root.querySelector( '[data-bot-id="bot-existing"]' )?.textContent
		).toContain( 'Updated Bot' );
	} );
} );
