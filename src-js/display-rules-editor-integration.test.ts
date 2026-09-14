import { normalizeDisplayRules } from './display-rules';
import { bootstrapAdminApp } from './index';

type TestElementProps = Record< string, unknown > | null;

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
			if (
				key === 'defaultValue' &&
				( element instanceof HTMLInputElement ||
					element instanceof HTMLTextAreaElement )
			) {
				element.defaultValue = String( value );
			}
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
	color_mode: 'dark',
	position: 'bottom-left',
	launcher_style: 'text',
	panel_size: 'large',
	radius_px: 24,
	font_family: 'mono',
};

const serverRules = normalizeDisplayRules( {
	visibility: {
		url_include: [ '/pricing' ],
		audience: 'authenticated',
	},
	starters: {
		default: [ 'Ask about pricing' ],
	},
	localization: {
		locale: 'en-us',
		direction: 'auto',
	},
} );

describe( 'display rules editor persistence integration', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'loads and saves normalized rules for only the selected bot', async () => {
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
		const fetcher = jest.fn(
			async ( input: RequestInfo | URL, init?: RequestInit ) => {
				const url = String( input );
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
				if ( url.includes( '/admin/bots?' ) ) {
					return {
						ok: true,
						status: 200,
						json: async () => ( {
							items: [ bot ],
							total: 1,
							page: 1,
							per_page: 20,
						} ),
					};
				}
				if ( url.endsWith( '/admin/bots/bot-existing/appearance' ) ) {
					return {
						ok: true,
						status: 200,
						json: async () => ( { appearance } ),
					};
				}
				if (
					url.endsWith( '/admin/bots/bot-existing/display-rules' )
				) {
					if ( init?.method === 'PUT' ) {
						return {
							ok: true,
							status: 200,
							json: async () => ( {
								display_rules: JSON.parse(
									String( init.body )
								),
							} ),
						};
					}
					return {
						ok: true,
						status: 200,
						json: async () => ( { display_rules: serverRules } ),
					};
				}
				throw new Error( `Unexpected fetch: ${ url }` );
			}
		);
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect( bootstrapAdminApp( '#/bots/bot-existing?page=1' ) ).toBe(
			true
		);
		await tick();
		await tick();
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots/bot-existing/display-rules',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);

		const include = root.querySelector< HTMLTextAreaElement >(
			'form[data-display-rules-editor] textarea[name="url_include"]'
		);
		const audience = root.querySelector< HTMLSelectElement >(
			'form[data-display-rules-editor] select[name="audience"]'
		);
		expect( include?.value ).toBe( '/pricing' );
		expect( audience?.value ).toBe( 'authenticated' );

		if ( include === null || audience === null ) {
			return;
		}

		include.value = '/pricing\n/docs\n/pricing';
		include.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		audience.value = 'anonymous';
		audience.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		root
			.querySelector< HTMLFormElement >(
				'form[data-display-rules-editor]'
			)
			?.dispatchEvent(
				new Event( 'submit', { bubbles: true, cancelable: true } )
			);
		await tick();
		await tick();

		const expected = normalizeDisplayRules( {
			...serverRules,
			visibility: {
				...serverRules.visibility,
				url_include: [ '/pricing', '/docs' ],
				audience: 'anonymous',
			},
		} );
		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/bots/bot-existing/display-rules',
			expect.objectContaining( {
				method: 'PUT',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
				body: JSON.stringify( expected ),
			} )
		);
		expect( JSON.stringify( expected ) ).not.toContain( 'provider_id' );
		expect( JSON.stringify( expected ) ).not.toContain( 'model_id' );
		expect( JSON.stringify( expected ) ).not.toContain( 'isAuthenticated' );
	} );
} );
