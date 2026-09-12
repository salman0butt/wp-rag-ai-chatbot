import { bootstrapAdminApp } from './index';

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if (
			key === 'key' ||
			key === 'onClick' ||
			key === 'onSubmit' ||
			value === undefined
		) {
			continue;
		}

		if ( typeof value === 'boolean' ) {
			if ( value ) {
				element.setAttribute( key, '' );
			}
			continue;
		}

		let attributeName = key;
		if ( key === 'htmlFor' ) {
			attributeName = 'for';
		} else if ( key === 'className' ) {
			attributeName = 'class';
		}
		element.setAttribute( attributeName, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const okJson = ( body: unknown ) => ( {
	ok: true,
	status: 200,
	json: async () => body,
} );

const sourcePage = ( page: number, id: number, title: string ) =>
	okJson( {
		items: [
			{
				id,
				source_key: `source-${ id }`,
				source_type: 'wordpress_posts',
				external_id: null,
				title,
				canonical_url: null,
				status: 'indexed',
				last_synced_at: null,
				updated_at: '2026-09-09T12:00:00+00:00',
			},
		],
		total: 40,
		page,
		per_page: 20,
	} );

const jobsPage = okJson( {
	items: [],
	total: 0,
	page: 1,
	per_page: 20,
} );

const flush = async (): Promise< void > => {
	for ( let tick = 0; tick < 5; tick += 1 ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}
};

const navigate = ( hash: string ): void => {
	window.history.replaceState( null, '', hash );
	window.dispatchEvent( new Event( 'hashchange' ) );
};

describe( 'knowledge page request ordering', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.history.replaceState( null, '', '#' );
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'keeps the newest source page authoritative when an older page resolves later', async () => {
		let resolvePageOne: (
			value: ReturnType< typeof sourcePage >
		) => void = () => undefined;
		const pageOne = new Promise< ReturnType< typeof sourcePage > >(
			( resolve ) => {
				resolvePageOne = resolve;
			}
		);
		const fetcher = jest.fn( ( input: RequestInfo | URL ) => {
			const url = String( input );

			if ( url.endsWith( '/admin/onboarding/readiness' ) ) {
				return Promise.resolve(
					okJson( { ready: true, next_step: 'complete' } )
				);
			}
			if (
				url.endsWith( '/admin/knowledge/sources?page=1&per_page=20' )
			) {
				return pageOne;
			}
			if (
				url.endsWith( '/admin/knowledge/sources?page=2&per_page=20' )
			) {
				return Promise.resolve(
					sourcePage( 2, 21, 'Newest Page Source' )
				);
			}
			if ( url.endsWith( '/admin/knowledge/jobs?page=1&per_page=20' ) ) {
				return Promise.resolve( jobsPage );
			}

			throw new Error( `Unexpected request: ${ url }` );
		} );
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
		const root = document.createElement( 'div' );
		root.id = 'wp-rag-ai-chatbot-admin';
		document.body.append( root );

		window.history.replaceState( null, '', '#/knowledge?page=1' );
		expect( bootstrapAdminApp() ).toBe( true );
		await flush();

		navigate( '#/knowledge?page=2' );
		await flush();

		expect( root.textContent ).toContain( 'Newest Page Source' );
		expect( root.textContent ).toContain( 'Page 2 of 2' );

		resolvePageOne( sourcePage( 1, 1, 'Stale Page Source' ) );
		await flush();

		expect( root.textContent ).toContain( 'Newest Page Source' );
		expect( root.textContent ).toContain( 'Page 2 of 2' );
		expect( root.textContent ).not.toContain( 'Stale Page Source' );
	} );
} );
