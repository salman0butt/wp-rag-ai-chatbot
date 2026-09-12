import * as plugin from './index';

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

		let attribute = key;
		if ( key === 'htmlFor' ) {
			attribute = 'for';
		} else if ( key === 'className' ) {
			attribute = 'class';
		}
		element.setAttribute( attribute, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const result = {
	ok: true,
	answer: 'Returns are accepted within 30 days.',
	no_answer: false,
	citations: [],
	model_id: 'gpt-test',
	latency_ms: 42,
	usage: {
		input_tokens: 10,
		output_tokens: 5,
		total_tokens: 15,
	},
	debug_trace: {
		query: { hash: 'abc', bytes: 27 },
		channels: { counts: {}, failures: {} },
		rerank_status: 'not_requested',
		candidates: [],
	},
};

const settle = async (): Promise< void > => {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
};

describe( 'Playground admin bootstrap', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'submits through the protected admin client and renders the bounded result', async () => {
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
		window.location.hash = '#/playground';
		Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
			configurable: true,
			value: {
				plugin: 'wp-rag-ai-chatbot',
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				nonce: 'rest-nonce',
			},
		} );

		const fetcher = jest.fn().mockImplementation( async ( url: string ) => {
			if ( url.endsWith( '/admin/onboarding/readiness' ) ) {
				return {
					ok: true,
					status: 200,
					json: async () => ( {
						ready: true,
						next_step: 'complete',
					} ),
				};
			}

			return {
				ok: true,
				status: 200,
				json: async () => result,
			};
		} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect( plugin.bootstrapAdminApp( '#/playground' ) ).toBe( true );
		await settle();

		const form = root.querySelector< HTMLFormElement >(
			'[data-playground-form]'
		);
		expect( form ).not.toBeNull();
		( form?.elements.namedItem( 'bot_id' ) as HTMLInputElement ).value =
			'support-bot';
		(
			form?.elements.namedItem( 'source_id' ) as HTMLInputElement
		).value = '9';
		(
			form?.elements.namedItem( 'collection_id' ) as HTMLInputElement
		).value = 'support-docs';
		(
			form?.elements.namedItem( 'question' ) as HTMLTextAreaElement
		).value = 'What is the return window?';
		form?.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await settle();

		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/debug/playground',
			expect.objectContaining( {
				method: 'POST',
				body: JSON.stringify( {
					bot_id: 'support-bot',
					source_id: 9,
					collection_id: 'support-docs',
					question: 'What is the return window?',
				} ),
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( root.textContent ).toContain( result.answer );
		expect( root.textContent ).toContain( result.model_id );
	} );
} );
