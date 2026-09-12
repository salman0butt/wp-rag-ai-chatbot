import { KnowledgeManagementScreen } from './index';

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

describe( 'knowledge document navigation', () => {
	it( 'renders the selected document as a keyboard-labelled route', () => {
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: {
				element: {
					createElement: createTestElement,
				},
			},
		} );
		const screen = KnowledgeManagementScreen as unknown as ( props: {
			page: {
				items: Array< Record< string, unknown > >;
				total: number;
				page: number;
				per_page: number;
			};
			selectedSourceId: string;
			selectedDocumentKey: string;
			detail: Record< string, unknown >;
			documents: {
				items: Array< Record< string, unknown > >;
				total: number;
				page: number;
				per_page: number;
			};
		} ) => Node;
		const root = document.createElement( 'div' );

		root.append(
			screen( {
				page: {
					items: [
						{
							id: 17,
							source_key: 'source-17',
							source_type: 'wordpress_posts',
							external_id: null,
							title: 'Support Source',
							canonical_url: null,
							status: 'indexed',
							last_synced_at: null,
							updated_at: '2026-09-08T19:05:00+00:00',
						},
					],
					total: 21,
					page: 2,
					per_page: 20,
				},
				selectedSourceId: '17',
				selectedDocumentKey: 'doc-support',
				detail: {
					id: 17,
					source_key: 'source-17',
					source_type: 'wordpress_posts',
					external_id: null,
					title: 'Support Source',
					canonical_url: null,
					status: 'indexed',
					last_synced_at: null,
					created_at: '2026-09-08T18:00:00+00:00',
					updated_at: '2026-09-08T19:05:00+00:00',
				},
				documents: {
					items: [
						{
							id: 31,
							document_key: 'doc-support',
							source_id: 17,
							external_id: null,
							document_type: 'post',
							title: 'Reset your password',
							canonical_url: null,
							source_version: '7',
							language: 'en',
							visibility: 'public',
							created_at: '2026-09-08T18:10:00+00:00',
							updated_at: '2026-09-08T19:00:00+00:00',
						},
					],
					total: 1,
					page: 1,
					per_page: 20,
				},
			} )
		);

		const link = root.querySelector(
			'[data-knowledge-document-key="doc-support"] a'
		);
		expect( link?.getAttribute( 'href' ) ).toBe(
			'#/knowledge/17/documents/doc-support?page=2'
		);
		expect( link?.getAttribute( 'aria-current' ) ).toBe( 'true' );
		expect( link?.textContent ).toBe( 'Reset your password' );
	} );
} );
