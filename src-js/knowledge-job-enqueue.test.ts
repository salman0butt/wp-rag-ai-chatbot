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

describe( 'knowledge job enqueue action', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'does not expose a browser-controlled legacy enqueue form or profile fields', async () => {
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce(
				okJson( { ready: true, next_step: 'complete' } )
			)
			.mockResolvedValueOnce(
				okJson( { items: [], total: 0, page: 1, per_page: 20 } )
			)
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

		expect(
			root.querySelector( 'form[data-knowledge-job-enqueue="true"]' )
		).toBeNull();
		expect(
			root.querySelector(
				'input[name="collection_id"], input[name="configuration_id"], input[name="generation"]'
			)
		).toBeNull();
		expect(
			root.querySelector( '[data-knowledge-job-key="job-enqueued"]' )
		).not.toBeNull();
		expect( fetcher ).toHaveBeenCalledTimes( 3 );
	} );
} );
