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

const issueCases = [
	{
		code: 'missing_credential',
		message:
			'Add a provider credential to load compatible generation models.',
	},
	{
		code: 'provider_unavailable',
		message:
			'This provider is currently unavailable. Try again or choose another provider.',
	},
	{
		code: 'unsupported_capability',
		message:
			'This provider does not offer compatible generation models. Choose another provider.',
	},
] as const;

describe( 'provider error states', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it.each( issueCases )(
		'renders $code as safe actionable provider guidance without leaking server messages',
		async ( { code, message } ) => {
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
					restBase:
						'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
					nonce: 'rest-nonce',
				},
			} );
			const response = (
				payload: unknown,
				ok = true,
				status = 200
			) => ( {
				ok,
				status,
				json: async () => payload,
			} );
			const fetcher = jest
				.fn()
				.mockResolvedValueOnce(
					response( { ready: false, next_step: 'model' } )
				)
				.mockResolvedValueOnce(
					response( {
						configured: code !== 'missing_credential',
						source:
							code === 'missing_credential' ? 'none' : 'managed',
					} )
				)
				.mockResolvedValueOnce(
					response(
						{
							code,
							message: 'upstream secret sk-should-never-render',
						},
						false,
						400
					)
				);
			Object.defineProperty( window, 'fetch', {
				configurable: true,
				value: fetcher,
			} );

			expect( bootstrapAdminApp( '#/providers/openai_direct' ) ).toBe(
				true
			);
			await tick();
			await tick();

			const alert = root.querySelector< HTMLElement >(
				'[data-provider-settings] [role="alert"]'
			);
			expect( alert ).not.toBeNull();
			expect( alert?.textContent ).toContain( message );
			expect( root.textContent ).not.toContain( 'upstream secret' );
			expect( root.textContent ).not.toContain(
				'sk-should-never-render'
			);
			expect(
				root.querySelector< HTMLInputElement >(
					'input[name="credential"]'
				)
			).not.toBeNull();
		}
	);
} );
