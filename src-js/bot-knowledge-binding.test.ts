import {
	BotKnowledgeBinding,
	deleteBotKnowledgeBinding,
	saveBotKnowledgeBinding,
	type BotRetrievalProjection,
	type KnowledgeSourceChoice,
} from './bot-knowledge-binding';
import { PublishTestScreen, type AdminReadiness } from './admin-ui';

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

const sources: KnowledgeSourceChoice[] = [
	{
		id: 17,
		title: 'Support guide',
		source_type: 'manual_text',
		status: 'active',
	},
	{
		id: 23,
		title: 'Store FAQ',
		source_type: 'faq',
		status: 'active',
	},
];

const emptyRetrieval: BotRetrievalProjection = {
	configured: false,
	source_id: null,
	source_title: null,
	collection_id: null,
	collection_ready: false,
};

const readiness = (
	overrides: Partial< AdminReadiness > = {}
): AdminReadiness => ( {
	ready: false,
	next_step: 'publish',
	configured_generation_provider: true,
	configured_gemini_embedding: true,
	model_available: true,
	source_count: 1,
	completed_index_present: true,
	enabled_bot_count: 1,
	bound_bot_present: true,
	publishable_bot_present: true,
	...overrides,
} );

beforeEach( () => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: { element: { createElement: createTestElement } },
	} );
} );

describe( 'bot knowledge binding', () => {
	it( 'shows an accessible unconfigured state and only persisted source choices', () => {
		const root = document.createElement( 'div' );
		root.append(
			BotKnowledgeBinding( {
				botId: 'bot-1',
				sources,
				retrieval: emptyRetrieval,
			} ) as Node
		);

		expect(
			root.querySelector( '[data-bot-knowledge-status]' )?.textContent
		).toContain( 'Not connected' );
		expect( root.querySelectorAll( 'select option' ) ).toHaveLength( 3 );
		expect(
			Array.from( root.querySelectorAll( 'select option' ) ).map(
				( option ) => option.getAttribute( 'value' )
			)
		).toEqual( [ '', '17', '23' ] );
		expect( root.textContent ).not.toContain( 'collection-' );
	} );

	it( 'renders the server-derived collection and supports save/disconnect states', () => {
		const onSave = jest.fn();
		const onDisconnect = jest.fn();
		const root = document.createElement( 'div' );
		root.append(
			BotKnowledgeBinding( {
				botId: 'bot-1',
				sources,
				retrieval: {
					configured: true,
					source_id: 17,
					source_title: 'Support guide',
					collection_id: 'wp-rag-default',
					collection_ready: true,
				},
				status: 'ready',
				onSave,
				onDisconnect,
			} ) as Node
		);

		expect( root.textContent ).toContain( 'wp-rag-default' );
		expect( root.textContent ).toContain( 'Support guide' );
		expect(
			root.querySelector( '[data-bot-knowledge-status]' )?.textContent
		).toContain( 'Connected and indexed' );
		expect(
			root.querySelector( 'button[data-bot-knowledge-disconnect]' )
		).not.toBeNull();

		const select = root.querySelector( 'select' ) as HTMLSelectElement;
		select.value = '23';
		select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		root
			.querySelector< HTMLFormElement >( 'form' )
			?.dispatchEvent(
				new Event( 'submit', { bubbles: true, cancelable: true } )
			);
		root
			.querySelector< HTMLButtonElement >(
				'[data-bot-knowledge-disconnect]'
			)
			?.click();

		expect( onSave ).toHaveBeenCalledWith( 23 );
		expect( onDisconnect ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'surfaces retrieval and source loading separately from an empty source list', () => {
		const root = document.createElement( 'div' );
		root.append(
			BotKnowledgeBinding( {
				botId: 'bot-1',
				sources: [],
				sourcesStatus: 'loading',
				retrieval: emptyRetrieval,
				status: 'loading',
			} ) as Node
		);

		expect(
			root.querySelector( '[data-bot-knowledge-status]' )?.textContent
		).toContain( 'Loading knowledge connection' );
		expect( root.textContent ).toContain( 'Loading saved sources' );
		expect( root.textContent ).not.toContain( 'No saved sources yet' );
		expect( root.querySelector( '[role="status"]' ) ).not.toBeNull();

		root.replaceChildren(
			BotKnowledgeBinding( {
				botId: 'bot-1',
				sources: [],
				sourcesStatus: 'error',
				sourcesError:
					'Knowledge sources could not be loaded. Try again.',
				retrieval: emptyRetrieval,
				status: 'ready',
			} ) as Node
		);

		expect( root.querySelector( '[role="alert"]' )?.textContent ).toContain(
			'Knowledge sources could not be loaded'
		);
	} );

	it( 'sends only the selected source ID or no body to the retrieval routes', async () => {
		const request = jest.fn().mockResolvedValue( {} );
		const client = { request };

		await saveBotKnowledgeBinding( client, 'bot-1', 23 );
		await deleteBotKnowledgeBinding( client, 'bot-1' );

		expect( request ).toHaveBeenNthCalledWith(
			1,
			'/admin/bots/bot-1/retrieval',
			{
				method: 'PUT',
				body: { source_id: 23 },
			}
		);
		expect( request ).toHaveBeenNthCalledWith(
			2,
			'/admin/bots/bot-1/retrieval',
			{
				method: 'DELETE',
			}
		);
	} );
} );

describe( 'publish readiness gating', () => {
	it( 'disables publish choices until every server prerequisite is true', () => {
		const root = document.createElement( 'div' );
		root.append(
			PublishTestScreen( {
				readiness: readiness( { completed_index_present: false } ),
				botId: 'bot-1',
				botPublishable: false,
			} ) as Node
		);

		expect( root.querySelector( '[data-publish-warning]' ) ).not.toBeNull();
		expect(
			root.querySelectorAll< HTMLButtonElement >( '[data-copy-publish]' )
		).toHaveLength( 4 );
		expect(
			Array.from(
				root.querySelectorAll< HTMLButtonElement >(
					'[data-copy-publish]'
				)
			).every( ( button ) => button.disabled )
		).toBe( true );

		root.replaceChildren(
			PublishTestScreen( {
				readiness: readiness(),
				botId: 'bot-1',
				botPublishable: true,
			} ) as Node
		);
		expect(
			Array.from(
				root.querySelectorAll< HTMLButtonElement >(
					'[data-copy-publish]'
				)
			).every( ( button ) => ! button.disabled )
		).toBe( true );
	} );

	it( 'never renders a fallback publish identifier without a verified bot', () => {
		const root = document.createElement( 'div' );
		root.append(
			PublishTestScreen( {
				readiness: readiness(),
				botPublishable: false,
			} ) as Node
		);

		expect( root.textContent ).not.toContain( 'BOT_ID' );
		expect( root.querySelectorAll( '[data-publish-card]' ) ).toHaveLength(
			0
		);
		expect( root.querySelector( '[data-publish-warning]' ) ).not.toBeNull();
	} );

	it( 'keeps Publish/Test disabled for a selected bot that the server marks unpublishable', () => {
		const root = document.createElement( 'div' );
		root.append(
			PublishTestScreen( {
				readiness: readiness(),
				botId: 'bot-disabled',
				botPublishable: false,
			} ) as Node
		);

		expect( root.textContent ).toContain( 'bot-disabled' );
		expect( root.querySelector( '[data-publish-warning]' ) ).not.toBeNull();
		expect(
			Array.from(
				root.querySelectorAll< HTMLButtonElement >(
					'[data-copy-publish]'
				)
			).every( ( button ) => button.disabled )
		).toBe( true );
	} );
} );
