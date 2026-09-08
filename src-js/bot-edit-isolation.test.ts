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

describe( 'bot editor record isolation', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'discards unsaved draft state when switching selected bot records', async () => {
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

		const botA = {
			id: 'bot-a',
			name: 'Alpha Bot',
			enabled: true,
			provider_id: 'openai',
			model_id: 'gpt-5-mini',
			version: 3,
			created_at: '2026-09-08T01:00:00+00:00',
			updated_at: '2026-09-08T01:00:00+00:00',
		};
		const botB = {
			id: 'bot-b',
			name: 'Beta Bot',
			enabled: true,
			provider_id: 'openai',
			model_id: 'gpt-5-mini',
			version: 5,
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
					items: [ botA, botB ],
					total: 2,
					page: 1,
					per_page: 20,
				} ),
			} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );
		window.location.hash = '#/bots/bot-a';

		expect( bootstrapAdminApp() ).toBe( true );
		await tick();
		await tick();

		const alphaEditor = root.querySelector(
			'form[data-bot-editor="edit"][data-edit-bot-id="bot-a"]'
		);
		const alphaName =
			alphaEditor?.querySelector< HTMLInputElement >(
				'input[name="name"]'
			);

		expect(
			root.querySelectorAll( 'form[data-bot-editor="edit"]' )
		).toHaveLength( 1 );
		expect( alphaName?.value ).toBe( 'Alpha Bot' );
		alphaName!.value = 'Unsaved Alpha Draft';

		window.location.hash = '#/bots/bot-b';
		window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );

		const betaEditor = root.querySelector(
			'form[data-bot-editor="edit"][data-edit-bot-id="bot-b"]'
		);
		const betaName =
			betaEditor?.querySelector< HTMLInputElement >(
				'input[name="name"]'
			);

		expect(
			root.querySelectorAll( 'form[data-bot-editor="edit"]' )
		).toHaveLength( 1 );
		expect( root.querySelector( '[data-edit-bot-id="bot-a"]' ) ).toBeNull();
		expect( betaName?.value ).toBe( 'Beta Bot' );
	} );
} );
