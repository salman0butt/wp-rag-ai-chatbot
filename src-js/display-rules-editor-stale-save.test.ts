import { normalizeDisplayRules } from './display-rules';
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
	let deferredSelectValue: string | undefined;

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

		if (
			( key === 'value' || key === 'defaultValue' ) &&
			( element instanceof HTMLInputElement ||
				element instanceof HTMLTextAreaElement ||
				element instanceof HTMLSelectElement )
		) {
			if ( element instanceof HTMLSelectElement ) {
				deferredSelectValue = String( value );
				continue;
			}

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

	if (
		deferredSelectValue !== undefined &&
		element instanceof HTMLSelectElement
	) {
		element.value = deferredSelectValue;
	}

	return element;
};

const tick = async (): Promise< void > => {
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
};

const appearance = {
	primary_color: '#1d4ed8',
	color_mode: 'light',
	position: 'bottom-right',
	launcher_style: 'icon',
	panel_size: 'medium',
	radius_px: 16,
	font_family: 'system',
};

const displayRules = ( path: string ) =>
	normalizeDisplayRules( { visibility: { url_include: [ path ] } } );

describe( 'display rules editor stale save protection', () => {
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
			json: () => Promise< {
				display_rules: ReturnType< typeof displayRules >;
			} >;
		} >();
		const appearanceResponse = {
			ok: true,
			status: 200,
			json: async () => ( { appearance } ),
		};
		const rulesResponse = ( path: string ) => ( {
			ok: true,
			status: 200,
			json: async () => ( { display_rules: displayRules( path ) } ),
		} );
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
			.mockResolvedValueOnce( appearanceResponse )
			.mockResolvedValueOnce( rulesResponse( '/alpha' ) )
			.mockImplementationOnce( () => staleSave.promise )
			.mockResolvedValueOnce( appearanceResponse )
			.mockResolvedValueOnce( rulesResponse( '/beta' ) )
			.mockResolvedValueOnce( appearanceResponse )
			.mockResolvedValueOnce( rulesResponse( '/fresh' ) );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );
		window.location.hash = '#/bots/bot-a';

		expect( bootstrapAdminApp() ).toBe( true );
		await tick();
		await tick();
		await tick();

		const alphaInclude = root.querySelector< HTMLTextAreaElement >(
			'form[data-display-rules-editor] textarea[name="url_include"]'
		);
		expect( alphaInclude?.value ).toBe( '/alpha' );
		alphaInclude!.value = '/stale';
		alphaInclude!.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		root
			.querySelector< HTMLFormElement >(
				'form[data-display-rules-editor]'
			)
			?.dispatchEvent(
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
			root.querySelector< HTMLTextAreaElement >(
				'form[data-display-rules-editor] textarea[name="url_include"]'
			)?.value
		).toBe( '/fresh' );

		staleSave.resolve( rulesResponse( '/stale' ) );
		await tick();
		await tick();

		expect(
			root.querySelector< HTMLTextAreaElement >(
				'form[data-display-rules-editor] textarea[name="url_include"]'
			)?.value
		).toBe( '/fresh' );
	} );
} );
