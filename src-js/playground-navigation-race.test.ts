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
	answer: 'STALE_PLAYGROUND_ANSWER',
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

const flush = async (): Promise< void > => {
	for ( let tick = 0; tick < 5; tick += 1 ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}
};

const navigate = ( hash: string ): void => {
	window.history.replaceState( null, '', hash );
	window.dispatchEvent( new Event( 'hashchange' ) );
};

describe( 'Playground navigation request ordering', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.history.replaceState( null, '', '#' );
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'does not surface an old completion after leaving and returning', async () => {
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
		Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
			configurable: true,
			value: {
				plugin: 'wp-rag-ai-chatbot',
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				nonce: 'rest-nonce',
			},
		} );
		let releasePlayground: ( () => void ) | undefined;
		const playgroundGate = new Promise< void >( ( resolve ) => {
			releasePlayground = resolve;
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

			await playgroundGate;
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
		const root = document.createElement( 'div' );
		root.id = 'wp-rag-ai-chatbot-admin';
		document.body.append( root );
		window.history.replaceState( null, '', '#/playground' );

		expect( bootstrapAdminApp() ).toBe( true );
		await flush();

		const form = root.querySelector< HTMLFormElement >(
			'[data-playground-form]'
		);
		expect( form ).not.toBeNull();
		const values: Record< string, string > = {
			bot_id: 'support-bot',
			source_id: '9',
			collection_id: 'support-docs',
			question: 'What is the return window?',
		};
		for ( const [ name, value ] of Object.entries( values ) ) {
			const field = form?.elements.namedItem( name );

			if (
				field instanceof HTMLInputElement ||
				field instanceof HTMLTextAreaElement
			) {
				field.value = value;
			}
		}
		form?.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		expect(
			root.querySelector( '[data-playground-status="loading"]' )
		).not.toBeNull();

		navigate( '#/onboarding' );
		await flush();
		releasePlayground?.();
		await flush();
		navigate( '#/playground' );
		await flush();

		expect( root.textContent ).not.toContain( result.answer );
		expect(
			root.querySelector( '[data-playground-status="success"]' )
		).toBeNull();
	} );
} );
