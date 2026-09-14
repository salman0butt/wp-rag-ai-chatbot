import { AdminShell } from './index';

type TestElement = {
	tagName: string;
	props: Record< string, unknown > | null;
	children: unknown[];
};

const createElement = (
	tagName: string,
	props: Record< string, unknown > | null,
	...children: unknown[]
): TestElement => ( { tagName, props, children } );

const serialize = ( value: unknown ): string => JSON.stringify( value );

describe( 'AdminShell conversation screens', () => {
	beforeEach( () => {
		Object.defineProperty( window, 'wp', {
			configurable: true,
			value: {
				element: {
					createElement,
					render: jest.fn(),
				},
			},
		} );
	} );

	it( 'renders the conversation inbox from the protected admin read model', () => {
		const output = AdminShell( {
			state: 'ready',
			screen: 'conversations',
			conversationList: {
				items: [
					{
						conversation_id: 'conversation-1',
						bot_id: 'bot-1',
						started_at: '2026-09-14 12:00:00',
						latest_message_at: '2026-09-14 12:01:00',
						message_count: 2,
					},
				],
				page: 1,
				per_page: 25,
			},
		} );

		expect( serialize( output ) ).toContain( 'data-conversation-inbox' );
		expect( serialize( output ) ).toContain( 'conversation-1' );
	} );

	it( 'renders the selected conversation transcript and destructive action boundary', () => {
		const output = AdminShell( {
			state: 'ready',
			screen: 'conversations',
			conversationDetail: {
				conversation_id: 'conversation-1',
				bot_id: 'bot-1',
				started_at: '2026-09-14 12:00:00',
				messages: [
					{
						role: 'user',
						content: 'Where is my order?',
						created_at: '2026-09-14 12:01:00',
					},
				],
			},
			conversationDeleteConfirmationOpen: false,
			onRequestConversationDelete: jest.fn(),
			onCancelConversationDelete: jest.fn(),
			onConfirmConversationDelete: jest.fn(),
		} );

		const serialized = serialize( output );
		expect( serialized ).toContain( 'data-conversation-detail' );
		expect( serialized ).toContain( 'Where is my order?' );
		expect( serialized ).toContain( 'data-request-delete' );
	} );
} );
