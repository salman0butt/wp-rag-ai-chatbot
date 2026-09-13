import { bootstrapAdminApp } from './index';

type TestElementProps = Record< string, unknown > | null;

type Deferred< T > = {
	promise: Promise< T >;
	resolve: ( value: T ) => void;
};

const createDeferred = < T >(): Deferred< T > => {
	let resolve!: ( value: T ) => void;
	const promise = new Promise< T >( ( resolver ) => {
		resolve = resolver;
	} );

	return { promise, resolve };
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

const appearance = ( primaryColor: string ) => ( {
	primary_color: primaryColor,
	color_mode: 'light',
	position: 'bottom-right',
	launcher_style: 'icon',
	panel_size: 'medium',
	radius_px: 16,
	font_family: 'system',
} );

describe( 'appearance customizer stale save protection', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'does not let an older save response replace a newer reload after switching away and back', async () => {
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
			...botA,
			id: 'bot-b',
			name: 'Beta Bot',
			version: 5,
		};
		const staleSave = createDeferred< {
			ok: boolean;
			status: number;
			json: () => Promise< { appearance: ReturnType< typeof appearance > } >;
		} >();
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
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { appearance: appearance( '#2563eb' ) } ),
			} )
			.mockImplementationOnce( () => staleSave.promise )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { appearance: appearance( '#7c3aed' ) } ),
			} )
			.mockResolvedValueOnce( {
				ok: true,
				status: 200,
				json: async () => ( { appearance: appearance( '#16a34a' ) } ),
			} );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );
		window.location.hash = '#/bots/bot-a';

		expect( bootstrapAdminApp() ).toBe( true );
		await tick();
		await tick();
		await tick();

		const alphaColor = root.querySelector< HTMLInputElement >(
			'form[data-appearance-customizer] input[name="primary_color"]'
		);
		expect( alphaColor?.value ).toBe( '#2563eb' );
		alphaColor!.value = '#dc2626';
		alphaColor!.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		root.querySelector< HTMLFormElement >(
			'form[data-appearance-customizer]'
		)?.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await tick();

		window.location.hash = '#/bots/bot-b';
		window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		await tick();
		await tick();

		window.location.hash = '#/bots/bot-a';
		window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		await tick();
		await tick();

		expect(
			root.querySelector< HTMLInputElement >(
				'form[data-appearance-customizer] input[name="primary_color"]'
			)?.value
		).toBe( '#16a34a' );

		staleSave.resolve( {
			ok: true,
			status: 200,
			json: async () => ( { appearance: appearance( '#dc2626' ) } ),
		} );
		await tick();
		await tick();

		expect(
			root.querySelector< HTMLInputElement >(
				'form[data-appearance-customizer] input[name="primary_color"]'
			)?.value
		).toBe( '#16a34a' );
	} );
} );
