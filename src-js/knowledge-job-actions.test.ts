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

const job = ( jobKey: string, status: string ) => ( {
	job_key: jobKey,
	type: 'index.document',
	status,
	attempts: status === 'failed' ? 1 : 0,
	max_attempts: 3,
	available_at: '2026-09-09T05:00:00+00:00',
	cancel_requested_at: null as string | null,
	progress_current: 0,
	progress_total: 10,
	progress_message: null,
	last_error_code: status === 'failed' ? 'provider_unavailable' : null,
	last_error_message:
		status === 'failed'
			? 'Indexing provider is temporarily unavailable.'
			: null,
	started_at: null,
	completed_at: null,
	created_at: '2026-09-09T05:00:00+00:00',
	updated_at: '2026-09-09T05:00:00+00:00',
} );

const jobPage = ( items: ReturnType< typeof job >[] ) => ( {
	items,
	total: items.length,
	page: 1,
	per_page: 20,
} );

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

describe( 'knowledge job lifecycle actions', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'posts cancel and refreshes bounded inventory server-authoritatively', async () => {
		const queued = job( 'job-queued', 'queued' );
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce(
				okJson( { ready: true, next_step: 'complete' } )
			)
			.mockResolvedValueOnce(
				okJson( { items: [], total: 0, page: 1, per_page: 20 } )
			)
			.mockResolvedValueOnce( okJson( jobPage( [ queued ] ) ) )
			.mockResolvedValueOnce(
				okJson( {
					...queued,
					cancel_requested_at: '2026-09-09T05:01:00+00:00',
				} )
			)
			.mockResolvedValueOnce(
				okJson(
					jobPage( [
						{
							...queued,
							cancel_requested_at: '2026-09-09T05:01:00+00:00',
						},
					] )
				)
			);
		const root = configureAdminRuntime( fetcher );

		window.location.hash = '#/knowledge';
		expect( bootstrapAdminApp() ).toBe( true );
		await tick();
		await tick();

		const cancel = root.querySelector< HTMLButtonElement >(
			'button[data-knowledge-job-action="cancel"][data-knowledge-job-key="job-queued"]'
		);
		expect( cancel ).not.toBeNull();
		cancel!.click();
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			4,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/jobs/job-queued/cancel',
			expect.objectContaining( {
				method: 'POST',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( fetcher ).toHaveBeenNthCalledWith(
			5,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/jobs?page=1&per_page=20',
			expect.objectContaining( {
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
	} );

	it( 'posts retry and refreshes bounded inventory server-authoritatively', async () => {
		const failed = job( 'job-failed', 'failed' );
		const retry = job( 'job-retry', 'queued' );
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce(
				okJson( { ready: true, next_step: 'complete' } )
			)
			.mockResolvedValueOnce(
				okJson( { items: [], total: 0, page: 1, per_page: 20 } )
			)
			.mockResolvedValueOnce( okJson( jobPage( [ failed ] ) ) )
			.mockResolvedValueOnce( okJson( retry ) )
			.mockResolvedValueOnce( okJson( jobPage( [ retry ] ) ) );
		const root = configureAdminRuntime( fetcher );

		window.location.hash = '#/knowledge';
		expect( bootstrapAdminApp() ).toBe( true );
		await tick();
		await tick();

		const retryButton = root.querySelector< HTMLButtonElement >(
			'button[data-knowledge-job-action="retry"][data-knowledge-job-key="job-failed"]'
		);
		expect( retryButton ).not.toBeNull();
		retryButton!.click();
		await tick();
		await tick();

		expect( fetcher ).toHaveBeenNthCalledWith(
			4,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/jobs/job-failed/retry',
			expect.objectContaining( {
				method: 'POST',
				headers: expect.objectContaining( {
					'X-WP-Nonce': 'rest-nonce',
				} ),
			} )
		);
		expect( fetcher ).toHaveBeenNthCalledWith(
			5,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/jobs?page=1&per_page=20',
			expect.any( Object )
		);
		expect(
			root.querySelector( '[data-knowledge-job-key="job-retry"]' )
		).not.toBeNull();
	} );
} );
