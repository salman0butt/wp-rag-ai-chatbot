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

describe( 'provider model resource persistence', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'loads generation models for the selected provider and refetches after provider routing changes', async () => {
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: {
				element: {
					createElement: createTestElement,
					render: ( element: Node, root: Element ) => {
						root.replaceChildren( element );
					},
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
		const response = ( payload: unknown ) => ( {
			ok: true,
			status: 200,
			json: async () => payload,
		} );
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce(
				response( { ready: false, next_step: 'model' } )
			)
			.mockResolvedValueOnce(
				response( { configured: true, source: 'managed' } )
			)
			.mockResolvedValueOnce(
				response( {
					provider_id: 'openai_direct',
					purpose: 'generation',
					capability: null,
					models: [
						{
							model_id: 'gpt-generation',
							display_name: 'GPT Generation',
						},
					],
				} )
			)
			.mockResolvedValueOnce(
				response( { configured: true, source: 'environment' } )
			)
			.mockResolvedValueOnce(
				response( {
					provider_id: 'openrouter_direct',
					purpose: 'generation',
					capability: null,
					models: [
						{
							model_id: 'router-generation',
							display_name: 'Router Generation',
						},
					],
				} )
			);
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect( bootstrapAdminApp( '#/providers/openai_direct' ) ).toBe( true );
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			3,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/models?provider_id=openai_direct&purpose=generation',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect(
			Array.from(
				root.querySelectorAll< HTMLOptionElement >(
					'select[name="model_id"] option'
				)
			).map( ( option ) => option.value )
		).toEqual( [ 'gpt-generation' ] );

		window.location.hash = '#/providers/openrouter_direct';
		window.dispatchEvent( new Event( 'hashchange' ) );
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			5,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/models?provider_id=openrouter_direct&purpose=generation',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( root.textContent ).toContain( 'Router Generation' );
		expect( root.textContent ).not.toContain( 'GPT Generation' );
	} );
} );
