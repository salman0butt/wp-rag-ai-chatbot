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

		if ( key === 'ref' && typeof value === 'function' ) {
			( value as ( node: HTMLElement ) => void )( element );
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

const serverAppearance = {
	primary_color: '#1d4ed8',
	color_mode: 'dark',
	position: 'bottom-left',
	launcher_style: 'text',
	panel_size: 'large',
	radius_px: 24,
	font_family: 'mono',
};

describe( 'appearance customizer persistence integration', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'loads the selected bot appearance and saves the exact normalized seven-field draft', async () => {
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

		const bot = {
			id: 'bot-existing',
			name: 'Existing Bot',
			enabled: true,
			provider_id: 'openai',
			model_id: 'gpt-5-mini',
			version: 7,
			created_at: '2026-09-08T01:00:00+00:00',
			updated_at: '2026-09-08T01:00:00+00:00',
		};
		const savedAppearance = {
			...serverAppearance,
			primary_color: '#dc2626',
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
					items: [ bot ],
					total: 1,
					page: 1,
					per_page: 20,
				} ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { appearance: serverAppearance } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { appearance: savedAppearance } ),
			} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect(
			bootstrapAdminApp( '#/bots/bot-existing?page=1' )
		).toBe( true );
		await tick();
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			3,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots/bot-existing/appearance',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);

		const color = root.querySelector< HTMLInputElement >(
			'form[data-appearance-customizer] input[name="primary_color"]'
		);
		expect( color?.value ).toBe( '#1d4ed8' );
		expect(
			root.querySelector< HTMLElement >( '[data-appearance-preview]' )
				?.dataset.wpRagAiChatbotPosition
		).toBe( 'bottom-left' );

		if ( color === null ) {
			return;
		}

		color.value = '#dc2626';
		color.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		const form = root.querySelector< HTMLFormElement >(
			'form[data-appearance-customizer]'
		);
		form?.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			4,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots/bot-existing/appearance',
			expect.objectContaining( {
				method: 'PUT',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
				body: JSON.stringify( savedAppearance ),
			} )
		);
		expect(
			root.querySelector< HTMLInputElement >(
				'form[data-appearance-customizer] input[name="primary_color"]'
			)?.value
		).toBe( '#dc2626' );
	} );
} );
