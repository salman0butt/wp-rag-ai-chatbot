import { bootstrapAdminApp } from './index';

type TestElement = {
	tagName: string;
	props: Record< string, unknown > | null;
	children: unknown[];
};

const createElement = (
	tagName: string,
	props: Record< string, unknown > | null,
	...children: unknown[]
): TestElement => ( { tagName, props, children } );

const response = ( payload: unknown ) => ( {
	ok: true,
	status: 200,
	json: async () => payload,
} );

const findProp = (
	value: unknown,
	prop: string
): unknown => {
	if ( typeof value !== 'object' || value === null ) {
		return undefined;
	}

	const element = value as Partial< TestElement >;
	if ( element.props !== null && element.props?.[ prop ] !== undefined ) {
		return element.props[ prop ];
	}

	for ( const child of element.children ?? [] ) {
		const found = findProp( child, prop );
		if ( found !== undefined ) {
			return found;
		}
	}

	return undefined;
};

describe( 'conversation admin delete bootstrap', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'deletes the selected conversation through the protected client and returns to the inbox', async () => {
		const fetcher = jest.fn( async ( requestUrl: string, request?: RequestInit ) => {
			if ( requestUrl.includes( '/admin/onboarding/readiness' ) ) {
				return response( { ready: true, next_step: 'complete' } );
			}
			if ( request?.method === 'DELETE' ) {
				return response( { deleted: true } );
			}
			if ( requestUrl.includes( '/admin/conversations/conv-1?' ) ) {
				return response( {
					conversation: {
						conversation_id: 'conv-1',
						bot_id: 'bot-1',
						started_at: '2026-09-14 10:00:00',
						messages: [],
					},
				} );
			}
			return response( { items: [], page: 1, per_page: 25 } );
		} );
		const render = jest.fn();
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: { element: { createElement, render } },
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
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );

		expect( bootstrapAdminApp( '#/conversations/conv-1' ) ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const rendered = render.mock.calls.at( -1 )?.[ 0 ];
		const requestDelete = findProp( rendered, 'onClick' );
		expect( typeof requestDelete ).toBe( 'function' );
		( requestDelete as () => void )();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const confirmation = render.mock.calls.at( -1 )?.[ 0 ];
		const confirmDelete = findProp( confirmation, 'onConfirmDelete' );
		expect( typeof confirmDelete ).toBe( 'function' );
		( confirmDelete as () => void )();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/conversations/conv-1',
			expect.objectContaining( { method: 'DELETE' } )
		);
		expect( window.location.hash ).toBe( '#/conversations' );
	} );
} );
