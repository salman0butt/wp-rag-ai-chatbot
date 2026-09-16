import {
	ModernAdminShell,
	PublishTestScreen,
	type AdminReadiness,
} from './admin-ui';

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( value === undefined || key === 'key' ) {
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
		if ( key === 'onClick' && typeof value === 'function' ) {
			element.addEventListener( 'click', value as EventListener );
			continue;
		}
		if ( key === 'onKeyDown' && typeof value === 'function' ) {
			element.addEventListener( 'keydown', value as EventListener );
			continue;
		}
		if ( value === true ) {
			element.setAttribute( key, '' );
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

const readiness: AdminReadiness = {
	ready: false,
	next_step: 'knowledge',
	configured_generation_provider: true,
	configured_gemini_embedding: true,
	model_available: true,
	source_count: 2,
	completed_index_present: false,
	enabled_bot_count: 1,
	bound_bot_present: false,
	publishable_bot_present: false,
};

beforeEach( () => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: { element: { createElement: createTestElement } },
	} );
} );

describe( 'modern admin shell', () => {
	it( 'renders the four labelled destinations with an accessible current page', () => {
		const root = document.createElement( 'div' );
		root.append(
			ModernAdminShell( {
				state: 'ready',
				screen: 'overview',
				readiness,
			} ) as Node
		);

		const links = Array.from(
			root.querySelectorAll( 'nav[aria-label="Primary"] a' )
		);
		expect( links.map( ( link ) => link.textContent ) ).toEqual( [
			'Overview',
			'Knowledge',
			'Chatbots',
			'Publish/Test',
		] );
		expect( links.map( ( link ) => link.getAttribute( 'href' ) ) ).toEqual(
			[ '#/overview', '#/knowledge', '#/bots', '#/publish' ]
		);
		expect(
			root.querySelector( 'a[aria-current="page"]' )?.textContent
		).toBe( 'Overview' );
	} );

	it( 'shows four server-truth readiness cards and routes the current setup action', () => {
		const root = document.createElement( 'div' );
		root.append(
			ModernAdminShell( {
				state: 'ready',
				screen: 'overview',
				readiness,
			} ) as Node
		);

		expect( root.querySelectorAll( '[data-readiness-card]' ) ).toHaveLength(
			4
		);
		expect( root.textContent ).toContain( 'Gemini connected' );
		expect( root.textContent ).toContain( '2 sources' );
		expect( root.textContent ).toContain( 'Add knowledge' );
		expect(
			root
				.querySelector( '[data-current-step] a' )
				?.getAttribute( 'href' )
		).toBe( '#/knowledge' );
		expect(
			root.querySelector( '[data-current-step]' )?.textContent
		).toContain( 'Continue setup' );
	} );

	it( 'chooses Knowledge when the legacy server step is complete but indexing is incomplete', () => {
		const root = document.createElement( 'div' );
		root.append(
			ModernAdminShell( {
				state: 'ready',
				screen: 'overview',
				readiness: {
					...readiness,
					next_step: 'complete',
					source_count: 1,
					completed_index_present: false,
				},
			} ) as Node
		);

		expect(
			root
				.querySelector( '[data-current-step] a' )
				?.getAttribute( 'href' )
		).toBe( '#/knowledge' );
	} );
} );

describe( 'publish/test screen', () => {
	it( 'renders accessible readiness status and all four safe publish snippets', () => {
		const root = document.createElement( 'div' );
		root.append(
			PublishTestScreen( {
				readiness: { ...readiness, next_step: 'publish' },
				botId: 'bot-123',
				botPublishable: true,
			} ) as Node
		);

		expect( root.querySelector( '[role="status"]' ) ).not.toBeNull();
		expect( root.querySelectorAll( '[data-publish-card]' ) ).toHaveLength(
			4
		);
		expect( root.textContent ).toContain(
			'[wp_rag_ai_chatbot bot="bot-123"]'
		);
		expect( root.textContent ).toContain(
			'[wp_rag_ai_chatbot_embed bot="bot-123"]'
		);
		expect( root.textContent ).toContain(
			'[wp_rag_ai_chatbot_fullscreen bot="bot-123"]'
		);
		expect( root.textContent ).toContain( 'RAG AI Chatbot block' );
	} );

	it( 'copies a publish snippet only after a button click and announces success', async () => {
		const writeText = jest.fn().mockResolvedValue( undefined );
		Object.defineProperty( navigator, 'clipboard', {
			configurable: true,
			value: { writeText },
		} );
		const root = document.createElement( 'div' );
		root.append(
			PublishTestScreen( {
				readiness: {
					...readiness,
					next_step: 'publish',
					completed_index_present: true,
					bound_bot_present: true,
					publishable_bot_present: true,
				},
				botId: 'bot-123',
				botPublishable: true,
			} ) as Node
		);

		expect( writeText ).not.toHaveBeenCalled();
		(
			root.querySelector(
				'[data-copy-publish="floating"]'
			) as HTMLElement
		 ).click();
		await Promise.resolve();

		expect( writeText ).toHaveBeenCalledWith(
			'[wp_rag_ai_chatbot bot="bot-123"]'
		);
		expect(
			root.querySelector( '[data-copy-status]' )?.textContent
		).toContain( 'Copied' );
	} );
} );
