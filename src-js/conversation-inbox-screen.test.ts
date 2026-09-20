import { ConversationInboxScreen } from './conversation-inbox-screen';

interface ConversationSummary {
	conversation_id: string;
	bot_id: string | null;
	started_at: string;
	latest_message_at: string | null;
	message_count: number;
}

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | number | undefined >
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
		if ( child === undefined ) {
			continue;
		}

		element.append( typeof child === 'number' ? String( child ) : child );
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

const conversation = (
	conversationId: string,
	botId: string | null,
	messageCount: number
): ConversationSummary => ( {
	conversation_id: conversationId,
	bot_id: botId,
	started_at: '2026-09-14 12:00:00',
	latest_message_at: '2026-09-14 12:05:00',
	message_count: messageCount,
} );

describe( 'ConversationInboxScreen', () => {
	beforeEach( configureTestRuntime );

	it( 'renders labelled inbox filters and semantic conversation rows', () => {
		const root = document.createElement( 'div' );
		root.append(
			ConversationInboxScreen( {
				items: [
					conversation( 'conv-alpha', 'bot-support', 4 ),
					conversation( 'conv-beta', null, 1 ),
				],
				page: 1,
				perPage: 25,
				hasNextPage: true,
			} ) as Node
		);

		expect(
			root.querySelector( 'label[for="conversation-search"]' )
				?.textContent
		).toBe( 'Search conversations' );
		expect(
			root.querySelector( 'label[for="conversation-bot"]' )?.textContent
		).toBe( 'Bot ID' );
		expect(
			root.querySelector( 'label[for="conversation-date-from"]' )
				?.textContent
		).toBe( 'From' );
		expect(
			root.querySelector( 'label[for="conversation-date-to"]' )
				?.textContent
		).toBe( 'To' );

		const rows = Array.from(
			root.querySelectorAll( '[data-conversation-id]' )
		);
		expect(
			rows.map( ( row ) => row.getAttribute( 'data-conversation-id' ) )
		).toEqual( [ 'conv-alpha', 'conv-beta' ] );
		expect( rows[ 0 ]?.textContent ).toContain( 'bot-support' );
		expect( rows[ 0 ]?.textContent ).toContain( '4 messages' );
		expect( rows[ 1 ]?.textContent ).toContain( 'Unassigned' );
		expect(
			rows[ 0 ]?.querySelector( '[data-conversation-link]' )?.getAttribute(
				'href'
			)
		).toBe( '#/conversations/conv-alpha?page=1' );
	} );

	it( 'renders a stable empty state and bounded pagination controls', () => {
		const emptyRoot = document.createElement( 'div' );
		emptyRoot.append(
			ConversationInboxScreen( {
				items: [],
				page: 1,
				perPage: 25,
				hasNextPage: false,
			} ) as Node
		);

		expect(
			emptyRoot.querySelector( '[data-conversation-list-empty]' )
				?.textContent
		).toBe( 'No conversations found.' );
		expect(
			emptyRoot
				.querySelector( 'button[data-page="previous"]' )
				?.hasAttribute( 'disabled' )
		).toBe( true );
		expect(
			emptyRoot
				.querySelector( 'button[data-page="next"]' )
				?.hasAttribute( 'disabled' )
		).toBe( true );
	} );
} );
