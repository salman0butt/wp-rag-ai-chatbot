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

const sourcePage = okJson( {
	items: [
		{
			id: 17,
			source_key: 'source-17',
			source_type: 'wordpress_posts',
			external_id: null,
			title: 'Source Seventeen',
			canonical_url: null,
			status: 'indexed',
			last_synced_at: null,
			updated_at: '2026-09-09T12:00:00+00:00',
		},
		{
			id: 18,
			source_key: 'source-18',
			source_type: 'wordpress_posts',
			external_id: null,
			title: 'Source Eighteen',
			canonical_url: null,
			status: 'indexed',
			last_synced_at: null,
			updated_at: '2026-09-09T12:00:00+00:00',
		},
	],
	total: 2,
	page: 1,
	per_page: 20,
} );

const jobsPage = okJson( {
	items: [],
	total: 0,
	page: 1,
	per_page: 20,
} );

const detail = ( id: number ) =>
	okJson( {
		id,
		source_key: `source-${ id }`,
		source_type: 'wordpress_posts',
		external_id: null,
		title: `Source ${ id } detail`,
		canonical_url: null,
		status: 'indexed',
		last_synced_at: null,
		created_at: '2026-09-09T11:00:00+00:00',
		updated_at: '2026-09-09T12:00:00+00:00',
	} );

const documents = ( sourceId: number, documentKey: string ) =>
	okJson( {
		items: [
			{
				id: sourceId * 10,
				document_key: documentKey,
				source_id: sourceId,
				external_id: null,
				document_type: 'post',
				title: `Document for source ${ sourceId }`,
				canonical_url: null,
				source_version: '1',
				language: 'en',
				visibility: 'public',
				created_at: '2026-09-09T11:00:00+00:00',
				updated_at: '2026-09-09T12:00:00+00:00',
			},
		],
		total: 1,
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

describe( 'knowledge navigation request ordering', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.history.replaceState( null, '', '#' );
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'ignores stale documents after a newer source selection has loaded', async () => {
		let resolveSource17Documents: (
			value: ReturnType< typeof documents >
		) => void = () => undefined;
		const source17Documents = new Promise< ReturnType< typeof documents > >(
			( resolve ) => {
				resolveSource17Documents = resolve;
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
				return Promise.resolve( sourcePage );
			}
			if ( url.endsWith( '/admin/knowledge/jobs?page=1&per_page=20' ) ) {
				return Promise.resolve( jobsPage );
			}
			if ( url.endsWith( '/admin/knowledge/sources/17' ) ) {
				return Promise.resolve( detail( 17 ) );
			}
			if ( url.endsWith( '/admin/knowledge/sources/18' ) ) {
				return Promise.resolve( detail( 18 ) );
			}
			if (
				url.endsWith(
					'/admin/knowledge/sources/17/documents?page=1&per_page=20'
				)
			) {
				return source17Documents;
			}
			if (
				url.endsWith(
					'/admin/knowledge/sources/18/documents?page=1&per_page=20'
				)
			) {
				return Promise.resolve( documents( 18, 'doc-18' ) );
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

		window.history.replaceState( null, '', '#/knowledge' );
		expect( bootstrapAdminApp() ).toBe( true );
		await flush();

		navigate( '#/knowledge/17' );
		await flush();

		navigate( '#/knowledge/18' );
		await flush();

		expect(
			root.querySelector( '[data-knowledge-selected-detail="18"]' )
		).not.toBeNull();
		expect(
			root.querySelector( '[data-knowledge-document-key="doc-18"]' )
		).not.toBeNull();

		resolveSource17Documents( documents( 17, 'doc-17' ) );
		await flush();

		expect(
			root.querySelector( '[data-knowledge-selected-detail="18"]' )
		).not.toBeNull();
		expect(
			root.querySelector( '[data-knowledge-document-key="doc-18"]' )
		).not.toBeNull();
		expect(
			root.querySelector( '[data-knowledge-document-key="doc-17"]' )
		).toBeNull();
	} );
} );
