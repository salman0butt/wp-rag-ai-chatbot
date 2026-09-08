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

describe( 'persisted provider credential replacement', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'writes only the new credential and refreshes secret-free server state', async () => {
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
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { ready: false, next_step: 'provider' } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { configured: true, source: 'managed' } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { managed: true } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { configured: true, source: 'managed' } ),
			} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect( bootstrapAdminApp( '#/providers/openai_direct' ) ).toBe( true );
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			2,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/providers/openai_direct/credential',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		const form = root.querySelector< HTMLFormElement >(
			'form[data-provider-credential-form]'
		);
		const input = form?.querySelector< HTMLInputElement >(
			'input[name="credential"]'
		);

		expect( root.textContent ).toContain( 'Credential configured' );
		expect( input?.value ).toBe( '' );
		expect( input?.getAttribute( 'value' ) ).toBeNull();
		if ( form === null || input === null || input === undefined ) {
			return;
		}

		input.value = 'sk-new-only';
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			3,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/providers/openai_direct/credential',
			expect.objectContaining( {
				method: 'PUT',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
				body: JSON.stringify( { credential: 'sk-new-only' } ),
			} )
		);
		expect( fetcher ).toHaveBeenNthCalledWith(
			4,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/providers/openai_direct/credential',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		const refreshedInput = root.querySelector< HTMLInputElement >(
			'input[name="credential"]'
		);
		expect( refreshedInput?.value ).toBe( '' );
		expect( root.innerHTML ).not.toContain( 'sk-new-only' );
	} );
} );
