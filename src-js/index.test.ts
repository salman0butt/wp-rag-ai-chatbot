import * as plugin from './index';

type AdminClientFactory = ( config: {
	baseUrl: string;
	nonce: string;
	fetcher: typeof fetch;
} ) => {
	request: < T >(
		path: string,
		options?: { method?: string; body?: unknown }
	) => Promise< T >;
};

type AdminShellState = 'loading' | 'empty' | 'error' | 'ready';
type AdminScreen =
	| 'onboarding'
	| 'bots'
	| 'providers'
	| 'knowledge'
	| 'playground';

type AdminShellComponent = ( props: {
	state: AdminShellState;
	screen?: AdminScreen;
} ) => Node;

type TestElementProps = Record< string, unknown > | null;
type TestRender = ( element: Node, root: Element ) => void;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( value === undefined || key === 'key' ) {
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

const configureTestElementRuntime = ( render?: TestRender ): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
				...( render ? { render } : {} ),
			},
		},
	} );
};

const renderAdminShell = (
	state: AdminShellState,
	screen?: AdminScreen
): HTMLElement => {
	configureTestElementRuntime();

	const exports = plugin as unknown as Record< string, unknown >;
	const AdminShell = exports.AdminShell;

	expect( typeof AdminShell ).toBe( 'function' );

	const root = document.createElement( 'div' );
	root.append( ( AdminShell as AdminShellComponent )( { state, screen } ) );

	return root;
};

describe( 'pluginIdentity', () => {
	it( 'uses the canonical plugin slug and development version', () => {
		expect( plugin.pluginIdentity ).toEqual( {
			slug: 'wp-rag-ai-chatbot',
			version: '0.1.0-dev',
		} );
	} );
} );

describe( 'createAdminApiClient', () => {
	it( 'sends WordPress REST nonce and JSON headers without putting the nonce in the URL', async () => {
		const exports = plugin as unknown as Record< string, unknown >;
		const createAdminApiClient = exports.createAdminApiClient;

		expect( typeof createAdminApiClient ).toBe( 'function' );

		const fetcher = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( { ready: true } ),
		} );
		const client = ( createAdminApiClient as AdminClientFactory )( {
			baseUrl: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			nonce: 'rest-nonce',
			fetcher: fetcher as unknown as typeof fetch,
		} );

		await expect(
			client.request< { ready: boolean } >( '/admin/bootstrap' )
		).resolves.toEqual( { ready: true } );
		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bootstrap',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( fetcher.mock.calls[ 0 ][ 0 ] ).not.toContain( 'rest-nonce' );
	} );

	it( 'normalizes failed REST responses without exposing arbitrary response messages', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: false,
			status: 403,
			json: async () => ( {
				code: 'rest_forbidden',
				message: 'provider-secret-upstream-detail',
			} ),
		} );
		const client = plugin.createAdminApiClient( {
			baseUrl: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			nonce: 'rest-nonce',
			fetcher: fetcher as unknown as typeof fetch,
		} );
		let failure: unknown;

		try {
			await client.request( '/admin/bootstrap' );
		} catch ( error ) {
			failure = error;
		}

		expect( failure ).toMatchObject( {
			name: 'AdminApiError',
			code: 'rest_forbidden',
			status: 403,
			message: 'The admin request could not be completed.',
		} );
		expect( ( failure as Error ).message ).not.toContain(
			'provider-secret-upstream-detail'
		);
	} );
} );

describe( 'AdminShell', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'announces loading state without exposing interactive content', () => {
		const root = renderAdminShell( 'loading' );

		expect( root.querySelector( '[role="status"]' )?.textContent ).toBe(
			'Loading administration data…'
		);
		expect( root.querySelector( 'nav' ) ).toBeNull();
	} );

	it( 'renders an explicit empty state', () => {
		const root = renderAdminShell( 'empty' );

		expect(
			root.querySelector( '[data-admin-state="empty"]' )?.textContent
		).toContain( 'No bots configured yet.' );
	} );

	it( 'announces a safe error without provider or server details', () => {
		const root = renderAdminShell( 'error' );

		expect( root.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'Administration data could not be loaded.'
		);
	} );

	it( 'renders accessible navigation and the selected ready screen', () => {
		const root = renderAdminShell( 'ready', 'bots' );
		const nav = root.querySelector( 'nav[aria-label="Administration"]' );
		const links = Array.from( nav?.querySelectorAll( 'a' ) ?? [] );

		expect( links.map( ( link ) => link.getAttribute( 'href' ) ) ).toEqual(
			[
				'#/onboarding',
				'#/bots',
				'#/providers',
				'#/knowledge',
				'#/playground',
			]
		);
		expect(
			nav?.querySelector( 'a[aria-current="page"]' )?.textContent
		).toBe( 'Bots' );
		expect( root.querySelector( 'main h1' )?.textContent ).toBe( 'Bots' );
	} );

	it( 'renders Playground as a first-class selected admin screen', () => {
		const root = renderAdminShell( 'ready', 'playground' );
		const nav = root.querySelector( 'nav[aria-label="Administration"]' );

		expect(
			nav?.querySelector( 'a[aria-current="page"]' )?.textContent
		).toBe( 'Playground' );
		expect( root.querySelector( 'main h1' )?.textContent ).toBe(
			'Playground'
		);
		expect( root.querySelector( '[data-playground-form]' ) ).not.toBeNull();
	} );

	it( 'uses the modern shell when the server returns bounded setup readiness', () => {
		configureTestElementRuntime();
		const AdminShell = ( plugin as unknown as Record< string, unknown > )
			.AdminShell as ( props: Record< string, unknown > ) => Node;
		const root = document.createElement( 'div' );

		root.append(
			AdminShell( {
				state: 'ready',
				screen: 'overview',
				readiness: {
					ready: false,
					next_step: 'knowledge',
					configured_generation_provider: true,
					configured_gemini_embedding: true,
					model_available: true,
					source_count: 1,
					completed_index_present: false,
					enabled_bot_count: 1,
					bound_bot_present: false,
					publishable_bot_present: false,
				},
			} )
		);

		expect(
			root.querySelector( 'nav[aria-label="Primary"]' )
		).not.toBeNull();
		expect( root.querySelectorAll( '[data-readiness-card]' ) ).toHaveLength(
			4
		);
		expect( root.textContent ).toContain( 'Gemini connected' );
	} );

	it( 'refreshes readiness after a bot mutation and updates the Overview snapshot', async () => {
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
		const initialReadiness = {
			ready: true,
			next_step: 'complete',
			configured_generation_provider: true,
			configured_gemini_embedding: true,
			model_available: true,
			source_count: 1,
			completed_index_present: false,
			enabled_bot_count: 0,
			bound_bot_present: false,
			publishable_bot_present: false,
		};
		const updatedReadiness = {
			...initialReadiness,
			completed_index_present: true,
			enabled_bot_count: 1,
			bound_bot_present: true,
			publishable_bot_present: true,
		};
		const createdBot = {
			id: 'bot-created',
			name: 'Created Bot',
			enabled: true,
			provider_id: 'gemini_direct',
			model_id: 'gemini-2.5-flash',
			version: 1,
			created_at: '2026-09-16T01:00:00+00:00',
			updated_at: '2026-09-16T01:00:00+00:00',
		};
		const readinessResponses = [
			initialReadiness,
			updatedReadiness,
			updatedReadiness,
		];
		let botPageCalls = 0;
		const fetcher = jest
			.fn()
			.mockImplementation(
				async ( url: string, options?: RequestInit ) => {
					if ( url.endsWith( '/admin/onboarding/readiness' ) ) {
						return {
							ok: true,
							status: 200,
							json: async () => readinessResponses.shift(),
						};
					}
					if ( url.endsWith( '/admin/bots?page=1&per_page=20' ) ) {
						botPageCalls += 1;
						return {
							ok: true,
							status: 200,
							json: async () =>
								botPageCalls === 1
									? {
											items: [],
											total: 0,
											page: 1,
											per_page: 20,
									  }
									: {
											items: [ createdBot ],
											total: 1,
											page: 1,
											per_page: 20,
									  },
						};
					}
					if (
						url.endsWith( '/admin/bots' ) &&
						options?.method === 'POST'
					) {
						return {
							ok: true,
							status: 201,
							json: async () => createdBot,
						};
					}
					return { ok: true, status: 200, json: async () => ( {} ) };
				}
			);
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		const bootstrapAdminApp = (
			plugin as unknown as Record< string, unknown >
		 ).bootstrapAdminApp as ( hash?: string ) => boolean;
		expect( bootstrapAdminApp( '#/bots' ) ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const form = root.querySelector< HTMLFormElement >(
			'form[data-bot-editor="create"]'
		);
		expect( form ).not.toBeNull();
		if ( form === null ) {
			return;
		}
		( form.elements.namedItem( 'name' ) as HTMLInputElement ).value =
			'Created Bot';
		( form.elements.namedItem( 'provider_id' ) as HTMLInputElement ).value =
			'gemini_direct';
		( form.elements.namedItem( 'model_id' ) as HTMLInputElement ).value =
			'gemini-2.5-flash';
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		window.history.replaceState( null, '', '#/overview' );
		window.dispatchEvent( new Event( 'hashchange' ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect(
			fetcher.mock.calls.filter( ( call ) =>
				String( call[ 0 ] ).endsWith( '/admin/onboarding/readiness' )
			)
		).toHaveLength( 3 );
		expect(
			root
				.querySelector( '[data-current-step] a' )
				?.getAttribute( 'href' )
		).toBe( '#/publish' );
		expect(
			root.querySelector( '[data-readiness-card="publish"]' )?.textContent
		).toContain( 'Ready to publish' );
	} );

	it( 'suppresses legacy headings inside the modern shell', () => {
		configureTestElementRuntime();
		const AdminShell = ( plugin as unknown as Record< string, unknown > )
			.AdminShell as ( props: Record< string, unknown > ) => Node;
		const root = document.createElement( 'div' );

		root.append(
			AdminShell( {
				state: 'ready',
				screen: 'bots',
				botPage: { items: [], total: 0, page: 1, per_page: 20 },
				readiness: {
					ready: false,
					next_step: 'first_bot',
					configured_generation_provider: true,
					configured_gemini_embedding: true,
					model_available: true,
					source_count: 0,
					completed_index_present: false,
					enabled_bot_count: 0,
					bound_bot_present: false,
					publishable_bot_present: false,
				},
			} )
		);

		expect( root.querySelectorAll( 'h1' ) ).toHaveLength( 1 );
	} );
} );

describe( 'resolveAdminScreen', () => {
	it( 'normalizes known hashes and falls back to onboarding', () => {
		const exports = plugin as unknown as Record< string, unknown >;
		const resolveAdminScreen = exports.resolveAdminScreen as
			| ( ( hash: string ) => AdminScreen )
			| undefined;

		expect( typeof resolveAdminScreen ).toBe( 'function' );
		expect( resolveAdminScreen?.( '#/bots' ) ).toBe( 'bots' );
		expect( resolveAdminScreen?.( '#/providers' ) ).toBe( 'providers' );
		expect( resolveAdminScreen?.( '#/playground' ) ).toBe( 'playground' );
		expect( resolveAdminScreen?.( '#/publish' ) ).toBe( 'publish' );
		expect( resolveAdminScreen?.( '#/unknown' ) ).toBe( 'onboarding' );
		expect( resolveAdminScreen?.( '' ) ).toBe( 'onboarding' );
	} );
} );

describe( 'bootstrapAdminApp', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.history.replaceState( null, '', '' );
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'does nothing when the admin mount boundary is absent', () => {
		const render = jest.fn();
		configureTestElementRuntime( render );
		const exports = plugin as unknown as Record< string, unknown >;
		const bootstrapAdminApp = exports.bootstrapAdminApp as
			| ( ( hash?: string ) => boolean )
			| undefined;

		expect( typeof bootstrapAdminApp ).toBe( 'function' );
		expect( bootstrapAdminApp?.( '#/bots' ) ).toBe( false );
		expect( render ).not.toHaveBeenCalled();
	} );

	it( 'uses one createRoot per admin container and skips legacy render', () => {
		const legacyRender = jest.fn();
		const rootRender = jest.fn();
		const createRoot = jest.fn( () => ( { render: rootRender } ) );
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: {
				element: {
					createElement: createTestElement,
					render: legacyRender,
					createRoot,
				},
			},
		} );
		const root = document.createElement( 'div' );
		root.id = 'wp-rag-ai-chatbot-admin';
		document.body.append( root );

		const bootstrapAdminApp = (
			plugin as unknown as Record< string, unknown >
		 ).bootstrapAdminApp as ( hash?: string ) => boolean;

		expect( bootstrapAdminApp( '#/bots' ) ).toBe( true );
		expect( bootstrapAdminApp( '#/bots' ) ).toBe( true );

		expect( createRoot ).toHaveBeenCalledTimes( 1 );
		expect( createRoot ).toHaveBeenCalledWith( root );
		expect( rootRender ).toHaveBeenCalledTimes( 2 );
		expect( legacyRender ).not.toHaveBeenCalled();
	} );

	it( 'falls back to legacy render when createRoot is unavailable', () => {
		const legacyRender = jest.fn();
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: {
				element: {
					createElement: createTestElement,
					render: legacyRender,
				},
			},
		} );
		const root = document.createElement( 'div' );
		root.id = 'wp-rag-ai-chatbot-admin';
		document.body.append( root );

		const bootstrapAdminApp = (
			plugin as unknown as Record< string, unknown >
		 ).bootstrapAdminApp as ( hash?: string ) => boolean;

		expect( bootstrapAdminApp( '#/bots' ) ).toBe( true );

		expect( legacyRender ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'mounts the selected ready screen from the safe WordPress boot payload', async () => {
		const render = jest.fn( ( element: Node, root: Element ) => {
			root.replaceChildren( element );
		} );
		configureTestElementRuntime( render );
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
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: jest.fn().mockResolvedValue( {
				ok: true,
				status: 200,
				json: async () => ( { ready: true, next_step: 'complete' } ),
			} ),
		} );
		const exports = plugin as unknown as Record< string, unknown >;
		const bootstrapAdminApp = exports.bootstrapAdminApp as
			| ( ( hash?: string ) => boolean )
			| undefined;

		expect( bootstrapAdminApp?.( '#/providers' ) ).toBe( true );
		expect( root.querySelector( '[role="status"]' )?.textContent ).toBe(
			'Loading administration data…'
		);

		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( render.mock.calls[ 0 ][ 1 ] ).toBe( root );
		expect(
			root.querySelector( 'a[aria-current="page"]' )?.textContent
		).toBe( 'Providers' );
		expect( root.querySelector( 'main h1' )?.textContent ).toBe(
			'Providers'
		);
	} );

	it.each( [
		[ 'initial request resolves first', 'initial-first' ],
		[ 'Overview request resolves first', 'overview-first' ],
		[
			'Overview succeeds and stale initial request rejects',
			'stale-rejection',
		],
	] as const )(
		'ignores stale initial readiness errors during the automatic onboarding-to-Overview transition when %s',
		async ( _label, responseOrder ) => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
			const render = jest.fn( ( element: Node, root: Element ) => {
				root.replaceChildren( element );
			} );
			configureTestElementRuntime( render );
			const root = document.createElement( 'div' );
			root.id = 'wp-rag-ai-chatbot-admin';
			document.body.append( root );
			Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
				configurable: true,
				value: {
					plugin: 'wp-rag-ai-chatbot',
					restBase:
						'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
					nonce: 'rest-nonce',
				},
			} );

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
			type DeferredResponse = {
				promise: Promise< {
					ok: true;
					status: 200;
					json: () => Promise< typeof readiness >;
				} >;
				resolve: ( value: {
					ok: true;
					status: 200;
					json: () => Promise< typeof readiness >;
				} ) => void;
				reject: ( reason?: unknown ) => void;
			};
			const deferredResponse = (): DeferredResponse => {
				let resolve: DeferredResponse[ 'resolve' ] = () => undefined;
				let reject: DeferredResponse[ 'reject' ] = () => undefined;
				const promise = new Promise<
					Awaited< DeferredResponse[ 'promise' ] >
				>( ( promiseResolve, promiseReject ) => {
					resolve = promiseResolve;
					reject = promiseReject;
				} );
				return { promise, resolve, reject };
			};
			const readinessRequests: Array< DeferredResponse > = [];
			const fetcher = jest.fn().mockImplementation( ( url: string ) => {
				if ( url.endsWith( '/admin/onboarding/readiness' ) ) {
					const request = deferredResponse();
					readinessRequests.push( request );
					return request.promise;
				}

				return Promise.resolve( {
					ok: true,
					status: 200,
					json: async () => ( {} ),
				} );
			} );
			Object.defineProperty( window, 'fetch', {
				configurable: true,
				value: fetcher,
			} );

			window.history.replaceState( null, '', '#/onboarding' );
			const exports = plugin as unknown as Record< string, unknown >;
			const bootstrapAdminApp = exports.bootstrapAdminApp as
				| ( ( hash?: string ) => boolean )
				| undefined;

			expect( bootstrapAdminApp?.() ).toBe( true );
			window.history.replaceState( null, '', '#/overview' );
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
			expect( readinessRequests ).toHaveLength( 2 );

			const initialRequest = readinessRequests[ 0 ];
			const overviewRequest = readinessRequests[ 1 ];
			if (
				initialRequest === undefined ||
				overviewRequest === undefined
			) {
				return;
			}

			const initialResponse = {
				ok: true as const,
				status: 200 as const,
				json: async () => readiness,
			};
			const overviewResponse = {
				ok: true as const,
				status: 200 as const,
				json: async () => readiness,
			};
			let alertAfterFirstResponse: Element | null = null;

			if ( responseOrder === 'initial-first' ) {
				initialRequest.resolve( initialResponse );
				await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
				alertAfterFirstResponse =
					root.querySelector( '[role="alert"]' );
				overviewRequest.resolve( overviewResponse );
			} else {
				overviewRequest.resolve( overviewResponse );
				await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
				alertAfterFirstResponse =
					root.querySelector( '[role="alert"]' );
				if ( responseOrder === 'overview-first' ) {
					initialRequest.resolve( initialResponse );
				} else {
					initialRequest.reject(
						new Error( 'stale initial readiness failure' )
					);
				}
			}

			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
			expect( alertAfterFirstResponse ).toBeNull();
			expect(
				root.querySelector( '[data-admin-ui-state="ready"]' )
			).not.toBeNull();
			expect( root.querySelector( '[role="alert"]' ) ).toBeNull();
		}
	);

	it( 'mounts automatically when the admin bundle loads on the plugin screen', () => {
		const render = jest.fn( ( element: Node, root: Element ) => {
			root.append( element );
		} );
		configureTestElementRuntime( render );
		const root = document.createElement( 'div' );
		root.id = 'wp-rag-ai-chatbot-admin';
		document.body.append( root );

		jest.isolateModules( () => {
			jest.requireActual( './index' );
		} );

		expect( render ).toHaveBeenCalledTimes( 1 );
		expect( render.mock.calls[ 0 ][ 1 ] ).toBe( root );
	} );
} );
