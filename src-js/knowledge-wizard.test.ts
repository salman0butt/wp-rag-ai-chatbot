import { bootstrapAdminApp, createAdminApiClient } from './index';
import {
	KnowledgeWizard,
	buildKnowledgeSourceRequest,
	createKnowledgeDraft,
	validateKnowledgeDraft,
	type KnowledgeSourceDraft,
} from './knowledge-wizard';

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
		if ( key === 'className' ) {
			element.className = String( value );
			continue;
		}
		if ( key === 'htmlFor' ) {
			element.setAttribute( 'for', String( value ) );
			continue;
		}
		if ( typeof value === 'boolean' ) {
			if ( value ) {
				element.setAttribute( key, '' );
			}
			continue;
		}
		element.setAttribute( key, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const configureElementRuntime = (): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: { element: { createElement: createTestElement } },
	} );
};

const manualDraft = (): KnowledgeSourceDraft => ( {
	sourceType: 'manual_text',
	title: 'Support guide',
	text: 'Reset your password from the account screen.',
	faqItems: [ { question: '', answer: '' } ],
	includePrivate: false,
	postTypes: [],
	woocommerceMode: 'catalog',
	productIds: '',
	file: null,
} );

describe( 'knowledge source request shaping', () => {
	it( 'uses server-owned WordPress defaults without profile metadata', () => {
		const request = buildKnowledgeSourceRequest( {
			...createKnowledgeDraft( 'wordpress_posts' ),
			title: '',
		} );

		expect( request ).toEqual( {
			kind: 'json',
			body: {
				source_type: 'wordpress_posts',
				config: { include_private: false },
			},
		} );
		expect( JSON.stringify( request ) ).not.toMatch(
			/collection|configuration|provider|embedding|path/i
		);
	} );

	it( 'validates and shapes manual text', () => {
		const draft = manualDraft();

		expect( validateKnowledgeDraft( draft, true ) ).toEqual( {} );
		expect( buildKnowledgeSourceRequest( draft ) ).toEqual( {
			kind: 'json',
			body: {
				source_type: 'manual_text',
				title: 'Support guide',
				config: { text: draft.text },
			},
		} );
		expect(
			validateKnowledgeDraft( { ...draft, text: ' ' }, true )
		).toHaveProperty( 'text' );
	} );

	it( 'requires complete FAQ rows and preserves only question/answer fields', () => {
		const draft = {
			...createKnowledgeDraft( 'faq' ),
			title: 'Support FAQ',
			faqItems: [
				{ question: 'How?', answer: 'Like this.' },
				{ question: 'Incomplete', answer: '' },
			],
		};

		expect( validateKnowledgeDraft( draft, true ) ).toHaveProperty(
			'faqItems'
		);
		const valid = { ...draft, faqItems: [ draft.faqItems[ 0 ] ] };
		expect( buildKnowledgeSourceRequest( valid ) ).toEqual( {
			kind: 'json',
			body: {
				source_type: 'faq',
				title: 'Support FAQ',
				config: {
					items: [ { question: 'How?', answer: 'Like this.' } ],
				},
			},
		} );
	} );

	it( 'supports WooCommerce catalog and explicit product selection only when available', () => {
		const catalog = {
			...createKnowledgeDraft( 'woocommerce_product' ),
			title: 'Products',
			woocommerceMode: 'catalog' as const,
		};
		expect( validateKnowledgeDraft( catalog, false ) ).toHaveProperty(
			'woocommerce'
		);
		expect( buildKnowledgeSourceRequest( catalog ) ).toEqual( {
			kind: 'json',
			body: {
				source_type: 'woocommerce_product',
				title: 'Products',
				config: { catalog: true },
			},
		} );

		const selected = {
			...catalog,
			woocommerceMode: 'selected' as const,
			productIds: '12, 19',
		};
		expect( validateKnowledgeDraft( selected, true ) ).toEqual( {} );
		expect( buildKnowledgeSourceRequest( selected ) ).toMatchObject( {
			body: {
				config: { product_ids: [ 12, 19 ] },
			},
		} );

		expect( validateKnowledgeDraft( catalog ).woocommerce ).toBe(
			'WooCommerce is not available on this site.'
		);
	} );

	it( 'builds multipart file requests without a browser-supplied path', () => {
		const file = new File( [ 'hello' ], 'guide.txt', {
			type: 'text/plain',
		} );
		const request = buildKnowledgeSourceRequest( {
			...createKnowledgeDraft( 'file' ),
			title: 'Guide upload',
			file,
		} );

		expect( request.kind ).toBe( 'formData' );
		if ( request.kind !== 'formData' ) {
			return;
		}
		expect( request.body.get( 'source_type' ) ).toBe( 'file' );
		expect( request.body.get( 'title' ) ).toBe( 'Guide upload' );
		expect( request.body.get( 'file' ) ).toBe( file );
		expect( request.body.has( 'path' ) ).toBe( false );
	} );
} );

describe( 'knowledge wizard', () => {
	beforeEach( configureElementRuntime );

	it( 'renders source cards, accessible errors, server job states, and protects duplicate submits', async () => {
		let resolveCreate: () => void = () => undefined;
		const create = jest.fn(
			() =>
				new Promise< void >( ( resolve ) => {
					resolveCreate = resolve;
				} )
		);
		const root = document.createElement( 'div' );
		root.append(
			KnowledgeWizard( {
				sources: [
					{
						id: 17,
						title: 'Support guide',
						source_type: 'manual_text',
						status: 'active',
					},
				],
				jobs: [
					{
						job_key: 'job-17',
						type: 'sync.source',
						status: 'running',
						progress_current: 1,
						progress_total: 2,
						last_error_code: null,
						last_error_message: null,
					},
				],
				onCreate: create,
			} ) as Node
		);

		expect(
			root.querySelector( '[data-knowledge-wizard]' )
		).not.toBeNull();
		expect(
			root.querySelectorAll( '[data-knowledge-source-type]' )
		).toHaveLength( 5 );
		expect( root.querySelector( '[role="alert"]' ) ).not.toBeNull();
		expect( root.textContent ).toContain( 'running' );

		const form = root.querySelector( 'form' ) as HTMLFormElement;
		const title = form.elements.namedItem( 'title' ) as HTMLInputElement;
		const text = form.elements.namedItem( 'text' ) as HTMLTextAreaElement;
		title.value = 'Support guide';
		text.value = 'Useful content';
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);

		expect( create ).toHaveBeenCalledTimes( 1 );
		expect(
			root.querySelector( '[data-knowledge-submit-status]' )?.textContent
		).toContain( 'Saving' );
		resolveCreate();
		await Promise.resolve();
	} );

	it( 'disables WooCommerce by default and clears a corrected validation alert', async () => {
		const create = jest.fn().mockResolvedValue( undefined );
		const root = document.createElement( 'div' );
		root.append( KnowledgeWizard( { onCreate: create } ) as Node );

		const wooCard = root.querySelector< HTMLElement >(
			'[data-knowledge-source-type="woocommerce_product"]'
		);
		expect( wooCard?.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect(
			wooCard?.querySelector< HTMLInputElement >( 'input' )?.disabled
		).toBe( true );

		const sourceType = root.querySelector< HTMLInputElement >(
			'input[name="source_type"][value="manual_text"]'
		);
		sourceType?.click();
		const form = root.querySelector( 'form' ) as HTMLFormElement;
		const title = form.elements.namedItem( 'title' ) as HTMLInputElement;
		const text = form.elements.namedItem( 'text' ) as HTMLTextAreaElement;
		title.value = 'Guide';
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);

		const error = root.querySelector< HTMLElement >(
			'[data-knowledge-wizard-error]'
		);
		expect( error?.getAttribute( 'aria-hidden' ) ).toBeNull();
		expect( error?.textContent ).toContain( 'Enter some text' );

		text.value = 'Corrected content';
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		expect( error?.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
		expect( error?.textContent ).toBe( '' );
		await Promise.resolve();
		expect( create ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'admin FormData requests', () => {
	it( 'keeps nonce, same-origin credentials, and Accept without JSON Content-Type', async () => {
		const fetcher = jest.fn().mockResolvedValue( {
			ok: true,
			status: 200,
			json: async () => ( {
				source: { id: 17 },
				job: { status: 'queued' },
			} ),
		} );
		const client = createAdminApiClient( {
			baseUrl: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			nonce: 'rest-nonce',
			fetcher: fetcher as unknown as typeof fetch,
		} );
		const form = new FormData();
		form.set( 'source_type', 'file' );

		await client.requestFormData( '/admin/knowledge/sources', form );

		const options = fetcher.mock.calls[ 0 ][ 1 ] as RequestInit;
		expect( options.credentials ).toBe( 'same-origin' );
		expect( options.body ).toBe( form );
		expect( options.headers ).toEqual( {
			Accept: 'application/json',
			'X-WP-Nonce': 'rest-nonce',
		} );
	} );
} );

describe( 'knowledge source refresh wiring', () => {
	const okJson = ( payload: unknown ) => ( {
		ok: true,
		status: 200,
		json: async () => payload,
	} );

	const sourcePage = ( title: string ) => ( {
		items: title
			? [
					{
						id: 17,
						source_key: 'source-17',
						source_type: 'manual_text',
						external_id: null,
						title,
						canonical_url: null,
						status: 'active',
						last_synced_at: null,
						updated_at: '2026-09-16T10:00:00+00:00',
					},
			  ]
			: [],
		total: title ? 1 : 0,
		page: 1,
		per_page: 20,
	} );

	const jobsPage = ( status: string ) => ( {
		items: status
			? [
					{
						job_key: 'job-17',
						type: 'sync.source',
						status,
						attempts: 0,
						max_attempts: 3,
						available_at: '2026-09-16T10:00:00+00:00',
						cancel_requested_at: null,
						progress_current: 0,
						progress_total: 1,
						progress_message: null,
						last_error_code: null,
						last_error_message: null,
						started_at: null,
						completed_at: null,
						created_at: '2026-09-16T10:00:00+00:00',
						updated_at: '2026-09-16T10:00:00+00:00',
					},
			  ]
			: [],
		total: status ? 1 : 0,
		page: 1,
		per_page: 20,
	} );

	it( 'refreshes source and job inventories from the server after creation', async () => {
		const render = jest.fn( ( element: Node, root: Element ) => {
			root.replaceChildren( element );
		} );
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: { element: { createElement: createTestElement, render } },
		} );
		Object.defineProperty( window, 'wpRagAiChatbotAdminConfig', {
			configurable: true,
			value: {
				plugin: 'wp-rag-ai-chatbot',
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				nonce: 'rest-nonce',
			},
		} );
		const fetcher = jest
			.fn()
			.mockResolvedValueOnce(
				okJson( { ready: true, next_step: 'knowledge' } )
			)
			.mockResolvedValueOnce( okJson( sourcePage( '' ) ) )
			.mockResolvedValueOnce( okJson( jobsPage( '' ) ) )
			.mockResolvedValueOnce(
				okJson( {
					source: { id: 17, title: 'Authoritative source' },
					job: { job_key: 'job-17', status: 'queued' },
				} )
			)
			.mockResolvedValueOnce(
				okJson( sourcePage( 'Authoritative source' ) )
			)
			.mockResolvedValueOnce( okJson( jobsPage( 'queued' ) ) );
		Object.defineProperty( window, 'fetch', {
			configurable: true,
			value: fetcher,
		} );
		const root = document.createElement( 'div' );
		root.id = 'wp-rag-ai-chatbot-admin';
		document.body.append( root );
		window.location.hash = '#/knowledge';

		expect( bootstrapAdminApp() ).toBe( true );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		const form = root.querySelector( 'form[data-knowledge-wizard-form]' );
		expect( form ).not.toBeNull();
		form?.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( fetcher ).toHaveBeenNthCalledWith(
			4,
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/admin/knowledge/sources',
			expect.objectContaining( { method: 'POST' } )
		);
		expect( fetcher ).toHaveBeenNthCalledWith(
			5,
			expect.stringContaining(
				'/admin/knowledge/sources?page=1&per_page=20'
			),
			expect.any( Object )
		);
		expect( fetcher ).toHaveBeenNthCalledWith(
			6,
			expect.stringContaining(
				'/admin/knowledge/jobs?page=1&per_page=20'
			),
			expect.any( Object )
		);
		expect( root.textContent ).toContain( 'Authoritative source' );
		expect( root.textContent ).toContain( 'queued' );
		expect( root.textContent ).not.toContain( 'completed' );
	} );
} );
