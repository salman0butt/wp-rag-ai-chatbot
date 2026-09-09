import * as plugin from './index';

interface KnowledgeSourceItem {
	id: string | number | null;
	source_key: string;
	source_type: string;
	external_id: string | null;
	title: string;
	canonical_url: string | null;
	status: string;
	last_synced_at: string | null;
	updated_at: string;
}

interface KnowledgeSourcePage {
	items: KnowledgeSourceItem[];
	total: number;
	page: number;
	per_page: number;
}

type KnowledgeManagementComponent = ( props: {
	page: KnowledgeSourcePage;
	selectedSourceId?: string;
} ) => Node;

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

		const attribute =
			key === 'htmlFor' ? 'for' : key === 'className' ? 'class' : key;
		element.setAttribute( attribute, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child );
		}
	}

	return element;
};

const configureTestRuntime = (): KnowledgeManagementComponent => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );
	const exports = plugin as unknown as Record< string, unknown >;

	return exports.KnowledgeManagementScreen as KnowledgeManagementComponent;
};

const source = ( id: string, title: string ): KnowledgeSourceItem => ( {
	id,
	source_key: `source-${ id }`,
	source_type: 'wordpress_posts',
	external_id: null,
	title,
	canonical_url: `https://example.test/${ id }`,
	status: 'indexed',
	last_synced_at: '2026-09-09T11:00:00+00:00',
	updated_at: '2026-09-09T11:05:00+00:00',
} );

describe( 'Knowledge manager responsive and keyboard structure', () => {
	it( 'provides the responsive root hook without truncating long selectable content', () => {
		const KnowledgeManagementScreen = configureTestRuntime();
		const root = document.createElement( 'div' );
		const longTitle = `Support-${ 'x'.repeat( 240 ) }`;

		root.append(
			KnowledgeManagementScreen( {
				page: {
					items: [ source( 'source-long', longTitle ) ],
					total: 2,
					page: 1,
					per_page: 1,
				},
				selectedSourceId: 'source-long',
			} )
		);

		const screen = root.querySelector(
			'[data-knowledge-management="list"]'
		);
		const selected = root.querySelector< HTMLAnchorElement >(
			'[data-knowledge-source-id="source-long"] a'
		);
		const next = root.querySelector< HTMLAnchorElement >(
			'[data-knowledge-page="next"]'
		);

		expect( screen?.classList ).toContain(
			'wp-rag-ai-chatbot-knowledge-management'
		);
		expect( selected?.tagName ).toBe( 'A' );
		expect( selected?.getAttribute( 'aria-current' ) ).toBe( 'true' );
		expect( selected?.textContent ).toBe( longTitle );
		expect( next?.tagName ).toBe( 'A' );
		expect( next?.getAttribute( 'href' ) ).toBe( '#/knowledge?page=2' );
	} );

	it( 'keeps the same responsive hook for the bounded empty state', () => {
		const KnowledgeManagementScreen = configureTestRuntime();
		const root = document.createElement( 'div' );

		root.append(
			KnowledgeManagementScreen( {
				page: { items: [], total: 0, page: 1, per_page: 20 },
			} )
		);

		expect(
			root.querySelector( '[data-knowledge-management="empty"]' )
				?.classList
		).toContain( 'wp-rag-ai-chatbot-knowledge-management' );
	} );
} );
