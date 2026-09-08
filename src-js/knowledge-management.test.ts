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

const configureTestRuntime = (): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );
};

const source = (
	id: string | number,
	title: string
): KnowledgeSourceItem => ( {
	id,
	source_key: `source-${ id }`,
	source_type: 'wordpress_posts',
	external_id: null,
	title,
	canonical_url: `https://example.test/${ id }`,
	status: 'indexed',
	last_synced_at: '2026-09-08T19:00:00+00:00',
	updated_at: '2026-09-08T19:05:00+00:00',
} );

describe( 'KnowledgeManagementScreen', () => {
	it( 'renders a server-supplied source page with selected context and labelled pagination', () => {
		configureTestRuntime();
		const exports = plugin as unknown as Record< string, unknown >;
		const KnowledgeManagementScreen = exports.KnowledgeManagementScreen;

		expect( typeof KnowledgeManagementScreen ).toBe( 'function' );

		const root = document.createElement( 'div' );
		root.append(
			( KnowledgeManagementScreen as KnowledgeManagementComponent )( {
				page: {
					items: [
						source( 'source-a', 'Support Articles' ),
						source( 'source-b', 'Product Catalog' ),
					],
					total: 3,
					page: 1,
					per_page: 2,
				},
				selectedSourceId: 'source-b',
			} )
		);

		const rows = Array.from(
			root.querySelectorAll( '[data-knowledge-source-id]' )
		);
		expect(
			rows.map( ( row ) =>
				row.getAttribute( 'data-knowledge-source-id' )
			)
		).toEqual( [ 'source-a', 'source-b' ] );
		expect(
			root.querySelector( '[aria-current="true"]' )?.textContent
		).toBe( 'Product Catalog' );
		expect(
			root.querySelector( '[data-knowledge-selected-source]' )
				?.textContent
		).toContain( 'Product Catalog' );
		expect(
			root.querySelector( '[aria-label="Knowledge source pagination"]' )
				?.textContent
		).toContain( 'Page 1 of 2' );
		expect(
			root
				.querySelector( '[data-knowledge-page="next"]' )
				?.getAttribute( 'href' )
		).toBe( '#/knowledge?page=2' );
	} );

	it( 'selects persisted numeric source IDs from the server DTO', () => {
		configureTestRuntime();
		const exports = plugin as unknown as Record< string, unknown >;
		const KnowledgeManagementScreen = exports.KnowledgeManagementScreen;
		const root = document.createElement( 'div' );

		root.append(
			( KnowledgeManagementScreen as KnowledgeManagementComponent )( {
				page: {
					items: [
						source( 11, 'Support Articles' ),
						source( 17, 'Product Catalog' ),
					],
					total: 2,
					page: 1,
					per_page: 20,
				},
				selectedSourceId: '17',
			} )
		);

		expect(
			root.querySelector( '[aria-current="true"]' )?.textContent
		).toBe( 'Product Catalog' );
		expect(
			root.querySelector( '[data-knowledge-selected-source]' )
				?.getAttribute( 'data-knowledge-selected-source' )
		).toBe( '17' );
	} );
} );
