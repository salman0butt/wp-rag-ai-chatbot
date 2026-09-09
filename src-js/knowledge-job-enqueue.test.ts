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

const okJson = ( payload: unknown ) => ( {
	ok: true,
	status: 200,
	json: async () => payload,
} );

const queuedJob = {
	job_key: 'job-enqueued',
	type: 'index.document',
	status: 'queued',
	attempts: 0,
	max_attempts: 3,
	available_at: '2026-09-09T07:30:00+00:00',
	cancel_requested_at: null,
	progress_current: 0,
	progress_total: 0,
	progress_message: null,
	last_error_code: null,
	last_error_message: null,
	started_at: null,
	completed_at: null,
	created_at: '2026-09-09T07:30:00+00:00',
	updated_at: '2026-09-09T07:30:00+00:00',
};

const configureAdminRuntime = ( fetcher: jest.Mock ): HTMLElement => {
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

	return root;
};

const setInput = ( root: HTMLElement, name: string, value: string ): void => {
	const input = root.querySelector< HTMLInputElement >( `input[name="${ name }"]` );
	expect( input ).not.toBeNull();
	input!.value = value;
};

describe( 'knowledge job enqueue action', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'posts the identifier-only payload and refreshes bounded inventory', async () => {
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce(
				okJson( { ready: true, next_step: 'complete' } )
			)
			.mockResolvedValueOnce(
				okJson( { items: [], total: 0, page: 1, per_page: 20 } )
			)
			.mockResolvedValueOnce(
				okJson( { items: [], total: 0, page: 1, per_page: 20 } )
			)
			.mockResolvedValueOnce( okJson( queuedJob ) )
			.mockResolvedValueOnce(
				okJson( {
					items: [ queuedJob ],
					total: 1,
					page: 1,
					per_page: 20,
				} )
			);
		const root = configureAdminRuntime( fetcher );

		window.location.hash = '#/knowledge';
		expect( bootstrapAdminApp() ).toBe( true );
		await tick();
		await tick();

		setInput( root, 'document_key', 'doc-support' );
		setInput( root, 'source_id', '17' );
		setInput( root, 'collection_id', 'support' );
		setInput( root, 'configuration_id', 'default-index' );
		setInput( root, 'generation', 'v2' );

		const form = root.querySelector< HTMLFormElement >(
			'form[data-knowledge-job-enqueue="true"]'
		);
		expect( form ).not.toBeNull();
		form!.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			4,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/jobs',
			expect.objectContaining( {
				method: 'POST',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
				body: JSON.stringify( {
					document_key: 'doc-support',
					source_id: 17,
					collection_id: 'support',
					configuration_id: 'default-index',
					generation: 'v2',
				} ),
			} )
		);
		expect( fetcher ).toHaveBeenNthCalledWith(
			5,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/jobs?page=1&per_page=20',
			expect.any( Object )
		);
		expect(
			root.querySelector( '[data-knowledge-job-key="job-enqueued"]' )
		).not.toBeNull();
	} );
} );
