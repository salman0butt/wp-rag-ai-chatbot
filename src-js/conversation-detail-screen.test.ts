import { ConversationDetailScreen } from './conversation-detail-screen';

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

describe( 'ConversationDetailScreen', () => {
	beforeEach( configureTestRuntime );

	it( 'renders the conversation transcript as ordered semantic messages', () => {
		const root = document.createElement( 'div' );
		root.append(
			ConversationDetailScreen( {
				conversation: {
					conversation_id: 'conv-alpha',
					bot_id: 'bot-support',
					started_at: '2026-09-14 12:00:00',
					messages: [
						{
							role: 'user',
							content: 'Where is my order?',
							created_at: '2026-09-14 12:00:01',
						},
						{
							role: 'assistant',
							content: 'I can help with that.',
							created_at: '2026-09-14 12:00:02',
						},
					],
				},
				deleteConfirmationOpen: false,
				onRequestDelete: jest.fn(),
				onCancelDelete: jest.fn(),
				onConfirmDelete: jest.fn(),
			} ) as Node
		);

		expect(
			root.querySelector( '[data-conversation-detail]' )?.getAttribute(
				'data-conversation-id'
			)
		).toBe( 'conv-alpha' );
		const messages = Array.from(
			root.querySelectorAll( '[data-conversation-message]' )
		);
		expect(
			messages.map( ( message ) =>
				message.getAttribute( 'data-message-role' )
			)
		).toEqual( [ 'user', 'assistant' ] );
		expect( messages[ 0 ]?.textContent ).toContain( 'Where is my order?' );
		expect( messages[ 1 ]?.textContent ).toContain( 'I can help with that.' );
	} );

	it( 'requires an explicit destructive confirmation before deletion', () => {
		const onRequestDelete = jest.fn();
		const onCancelDelete = jest.fn();
		const onConfirmDelete = jest.fn();
		const root = document.createElement( 'div' );
		root.append(
			ConversationDetailScreen( {
				conversation: {
					conversation_id: 'conv-alpha',
					bot_id: null,
					started_at: '2026-09-14 12:00:00',
					messages: [],
				},
				deleteConfirmationOpen: true,
				onRequestDelete,
				onCancelDelete,
				onConfirmDelete,
			} ) as Node
		);

		expect( root.querySelector( '[role="alertdialog"]' ) ).not.toBeNull();
		expect(
			root.querySelector( '[data-confirm-delete]' )?.textContent
		).toBe( 'Delete conversation' );

		( root.querySelector( '[data-cancel-delete]' ) as HTMLButtonElement ).click();
		( root.querySelector( '[data-confirm-delete]' ) as HTMLButtonElement ).click();

		expect( onCancelDelete ).toHaveBeenCalledTimes( 1 );
		expect( onConfirmDelete ).toHaveBeenCalledTimes( 1 );
		expect( onRequestDelete ).not.toHaveBeenCalled();
	} );
} );
