import { bootstrapAdminApp } from './index';

type TestElementProps = Record< string, unknown > | null;
type MockResponse = {
	ok: boolean;
	status: number;
	json: () => Promise< unknown >;
};
type Deferred = {
	promise: Promise< MockResponse >;
	resolve: ( response: MockResponse ) => void;
};

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
		if ( key === 'className' ) {
			element.className = String( value );
		} else if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
			element.addEventListener(
				key.slice( 2 ).toLowerCase(),
				value as EventListener
			);
		} else if ( typeof value === 'boolean' ) {
			if ( value ) {
				element.setAttribute( key, '' );
			}
		} else {
			element.setAttribute(
				key === 'htmlFor' ? 'for' : key,
				String( value )
			);
		}
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const response = ( payload: unknown, ok = true ): MockResponse => ( {
	ok,
	status: ok ? 200 : 500,
	json: async () => payload,
} );

const deferred = (): Deferred => {
	let resolve!: Deferred[ 'resolve' ];
	const promise = new Promise< MockResponse >( ( resolver ) => {
		resolve = resolver;
	} );
	return { promise, resolve };
};

const tick = async (): Promise< void > => {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
};

const flush = async (): Promise< void > => {
	for ( let index = 0; index < 8; index += 1 ) {
		await tick();
	}
};

const readiness = {
	ready: true,
	next_step: 'complete',
	configured_generation_provider: true,
	configured_gemini_embedding: true,
	model_available: true,
	source_count: 1,
	completed_index_present: true,
	enabled_bot_count: 1,
	bound_bot_present: true,
	publishable_bot_present: true,
};

const bot = {
	id: 'bot-a',
	name: 'Alpha Bot',
	enabled: true,
	provider_id: 'gemini_direct',
	model_id: 'gemini-2.5-flash',
	version: 1,
	created_at: '2026-09-16T01:00:00+00:00',
	updated_at: '2026-09-16T01:00:00+00:00',
};

const retrieval = ( overrides: Record< string, unknown > = {} ) => ( {
	configured: true,
	source_id: 17,
	source_title: 'Support guide',
	collection_id: 'wp-rag-default',
	collection_ready: true,
	...overrides,
} );

const configureAdmin = ( hash: string ): HTMLElement => {
	const render = ( element: Node, root: Element ): void => {
		root.replaceChildren( element );
	};
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: { element: { createElement: createTestElement, render } },
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
	window.history.replaceState( null, '', hash );
	return root;
};

const navigate = async ( hash: string ): Promise< void > => {
	window.history.replaceState( null, '', hash );
	window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
	await tick();
};

const commonBotResponse = ( url: string ): MockResponse | undefined => {
	if ( url.endsWith( '/admin/onboarding/readiness' ) ) {
		return response( readiness );
	}
	if ( url.endsWith( '/admin/bots?page=1&per_page=20' ) ) {
		return response( { items: [ bot ], total: 1, page: 1, per_page: 20 } );
	}
	if ( url.endsWith( '/appearance' ) ) {
		return response( { appearance: {} } );
	}
	if ( url.endsWith( '/display-rules' ) ) {
		return response( { display_rules: {} } );
	}
	if ( url.includes( '/admin/knowledge/sources?' ) ) {
		return response( {
			items: [
				{ id: 17, title: 'Support guide', source_type: 'manual_text' },
				{ id: 23, title: 'Store FAQ', source_type: 'faq' },
			],
			total: 2,
			page: 1,
			per_page: 100,
		} );
	}
	return undefined;
};

describe( 'Task 8 review round 2 controller behavior', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.history.replaceState( null, '', '' );
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'reloads retrieval after an initial load is superseded by Overview navigation', async () => {
		const root = configureAdmin( '#/bots/bot-a' );
		const retrievalRequests: Deferred[] = [];
		const fetcher = jest.fn( ( input: RequestInfo | URL ) => {
			const url = String( input );
			if ( url.endsWith( '/retrieval' ) ) {
				const request = deferred();
				retrievalRequests.push( request );
				return request.promise;
			}
			return Promise.resolve(
				commonBotResponse( url ) ?? response( {} )
			);
		} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		bootstrapAdminApp();
		await flush();
		expect( retrievalRequests ).toHaveLength( 1 );

		await navigate( '#/overview' );
		await navigate( '#/bots/bot-a' );
		expect( retrievalRequests ).toHaveLength( 2 );

		retrievalRequests[ 1 ]?.resolve(
			response( {
				retrieval: retrieval( {
					configured: false,
					source_id: null,
					source_title: null,
					collection_id: null,
					collection_ready: false,
				} ),
			} )
		);
		await flush();
		retrievalRequests[ 0 ]?.resolve(
			response( {
				retrieval: retrieval( { source_title: 'Stale source' } ),
			} )
		);
		await flush();

		expect( root.textContent ).not.toContain( 'Stale source' );
	} );

	it( 'reloads retrieval after an in-flight save is superseded by Overview navigation', async () => {
		const root = configureAdmin( '#/bots/bot-a' );
		const saveRequest = deferred();
		const retrievalRequests: Array<
			Promise< MockResponse > | MockResponse
		> = [ response( { retrieval: retrieval() } ) ];
		const fetcher = jest.fn(
			( input: RequestInfo | URL, init?: RequestInit ) => {
				const url = String( input );
				if ( url.endsWith( '/retrieval' ) && init?.method === 'PUT' ) {
					return saveRequest.promise;
				}
				if ( url.endsWith( '/retrieval' ) ) {
					const next =
						retrievalRequests.shift() ?? deferred().promise;
					return Promise.resolve( next );
				}
				return Promise.resolve(
					commonBotResponse( url ) ?? response( {} )
				);
			}
		);
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		bootstrapAdminApp();
		await flush();
		const select = root.querySelector< HTMLSelectElement >(
			'[data-bot-knowledge-form] select'
		);
		expect( select ).not.toBeNull();
		if ( select === null ) {
			return;
		}
		select.value = '23';
		root
			.querySelector< HTMLFormElement >( '[data-bot-knowledge-form]' )
			?.dispatchEvent(
				new Event( 'submit', { bubbles: true, cancelable: true } )
			);
		await tick();

		await navigate( '#/overview' );
		await navigate( '#/bots/bot-a' );
		expect(
			fetcher.mock.calls.filter(
				( call ) =>
					String( call[ 0 ] ).endsWith( '/retrieval' ) &&
					( call[ 1 ] as RequestInit | undefined )?.method !== 'PUT'
			)
		).toHaveLength( 2 );

		saveRequest.resolve(
			response( {
				retrieval: retrieval( { source_title: 'Stale save' } ),
			} )
		);
		await flush();
		expect( root.textContent ).not.toContain( 'Stale save' );
	} );

	it( 'surfaces safe retrieval and source load failures in the binding controller', async () => {
		const root = configureAdmin( '#/bots/bot-a' );
		const fetcher = jest.fn( ( input: RequestInfo | URL ) => {
			const url = String( input );
			if (
				url.endsWith( '/retrieval' ) ||
				url.includes( '/admin/knowledge/sources?' )
			) {
				return Promise.reject( new Error( 'opaque failure' ) );
			}
			return Promise.resolve(
				commonBotResponse( url ) ?? response( {} )
			);
		} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		bootstrapAdminApp();
		await flush();

		expect(
			root.querySelector( '[data-bot-knowledge-binding]' )
		).not.toBeNull();
		expect( root.textContent ).toContain(
			'Knowledge connection could not be loaded'
		);
		expect( root.textContent ).toContain(
			'Knowledge sources could not be loaded'
		);
		expect( root.querySelector( '[role="alert"]' ) ).not.toBeNull();
	} );

	it( 'surfaces safe save and disconnect failures in the binding controller', async () => {
		const root = configureAdmin( '#/bots/bot-a' );
		const fetcher = jest.fn(
			( input: RequestInfo | URL, init?: RequestInit ) => {
				const url = String( input );
				if ( url.endsWith( '/retrieval' ) && init?.method === 'PUT' ) {
					return Promise.reject( new Error( 'save failure' ) );
				}
				if (
					url.endsWith( '/retrieval' ) &&
					init?.method === 'DELETE'
				) {
					return Promise.reject( new Error( 'disconnect failure' ) );
				}
				if ( url.endsWith( '/retrieval' ) ) {
					return Promise.resolve(
						response( { retrieval: retrieval() } )
					);
				}
				return Promise.resolve(
					commonBotResponse( url ) ?? response( {} )
				);
			}
		);
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		bootstrapAdminApp();
		await flush();
		const select = root.querySelector< HTMLSelectElement >(
			'[data-bot-knowledge-form] select'
		);
		expect( select ).not.toBeNull();
		if ( select === null ) {
			return;
		}
		select.value = '23';
		root
			.querySelector< HTMLFormElement >( '[data-bot-knowledge-form]' )
			?.dispatchEvent(
				new Event( 'submit', { bubbles: true, cancelable: true } )
			);
		await flush();
		expect( root.textContent ).toContain(
			'Knowledge connection could not be saved'
		);

		root
			.querySelector< HTMLButtonElement >(
				'[data-bot-knowledge-disconnect]'
			)
			?.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
		await flush();
		expect( root.textContent ).toContain(
			'Knowledge connection could not be disconnected'
		);
	} );

	it( 'uses a verified direct publish bot and disables an incompatible selected bot', async () => {
		const root = configureAdmin( '#/publish/bot-disabled' );
		const disabledBot = { ...bot, id: 'bot-disabled', enabled: false };
		const fetcher = jest.fn( ( input: RequestInfo | URL ) => {
			const url = String( input );
			if ( url.endsWith( '/admin/bots/bot-disabled' ) ) {
				return Promise.resolve( response( { bot: disabledBot } ) );
			}
			if ( url.includes( '/admin/models?' ) ) {
				return Promise.resolve( response( { models: [] } ) );
			}
			if ( url.endsWith( '/retrieval' ) ) {
				return Promise.resolve(
					response( { retrieval: retrieval() } )
				);
			}
			return Promise.resolve(
				commonBotResponse( url ) ?? response( {} )
			);
		} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		bootstrapAdminApp();
		await flush();

		expect(
			fetcher.mock.calls.some( ( call ) =>
				String( call[ 0 ] ).endsWith( '/admin/bots/bot-disabled' )
			)
		).toBe( true );
		expect( root.textContent ).toContain( 'bot-disabled' );
		expect( root.textContent ).not.toContain( 'BOT_ID' );
		expect( root.querySelector( '[data-publish-warning]' ) ).not.toBeNull();
		expect(
			Array.from(
				root.querySelectorAll< HTMLButtonElement >(
					'[data-copy-publish]'
				)
			).every( ( button ) => button.disabled )
		).toBe( true );
	} );
} );
