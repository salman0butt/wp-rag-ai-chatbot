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

const errorJson = ( code: string, message: string ) => ( {
	ok: false,
	status: 409,
	json: async () => ( {
		code,
		message,
		data: { status: 409 },
	} ),
} );

const queuedJob = {
	job_key: 'job-queued',
	type: 'index.document',
	status: 'queued',
	attempts: 0,
	max_attempts: 3,
	available_at: '2026-09-09T08:30:00+00:00',
	cancel_requested_at: null,
	progress_current: 0,
	progress_total: 10,
	progress_message: null,
	last_error_code: null,
	last_error_message: null,
	started_at: null,
	completed_at: null,
	created_at: '2026-09-09T08:30:00+00:00',
	updated_at: '2026-09-09T08:30:00+00:00',
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

describe( 'knowledge job mutation errors', () => {
	afterEach( () => {
		document.body.innerHTML = '';
		window.location.hash = '';
		Reflect.deleteProperty( window, 'wpRagAiChatbotAdminConfig' );
		Reflect.deleteProperty( window, 'fetch' );
	} );

	it( 'surfaces invalid_transition safely without rendering backend detail', async () => {
		const secretBackendDetail = 'provider-token=should-never-render';
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
			)
			.mockResolvedValueOnce(
				errorJson( 'invalid_transition', secretBackendDetail )
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

		const alert = root.querySelector< HTMLElement >(
			'[role="alert"][data-knowledge-job-error="invalid_transition"]'
		);
		expect( alert ).not.toBeNull();
		expect( alert!.textContent ).toContain(
			'The job state changed. Refresh and try the action again.'
		);
		expect( root.textContent ).not.toContain( secretBackendDetail );
	} );

	it( 'uses a stable generic message for other mutation failures', async () => {
		const secretBackendDetail =
			'raw-provider-exception-should-never-render';
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
			)
			.mockResolvedValueOnce(
				errorJson( 'admin_request_failed', secretBackendDetail )
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

		const alert = root.querySelector< HTMLElement >(
			'[role="alert"][data-knowledge-job-error="admin_request_failed"]'
		);
		expect( alert ).not.toBeNull();
		expect( alert!.textContent ).toContain(
			'The job action could not be completed. Try again.'
		);
		expect( root.textContent ).not.toContain( secretBackendDetail );
	} );
} );
