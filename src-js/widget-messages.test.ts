type WidgetMessageKey =
	| 'chat'
	| 'open_chat'
	| 'chat_label'
	| 'close'
	| 'close_chat'
	| 'message'
	| 'send'
	| 'send_message'
	| 'retry'
	| 'copy'
	| 'copy_assistant_message'
	| 'sources'
	| 'assistant_typing'
	| 'sending'
	| 'rate_limited'
	| 'chat_unavailable'
	| 'send_failed';

type WidgetMessagesModule = {
	resolveWidgetMessage: (
		locale: string | undefined,
		key: WidgetMessageKey,
		params?: { botName?: string }
	) => string;
};

const loadMessages = (): WidgetMessagesModule =>
	jest.requireActual< WidgetMessagesModule >( './widget-messages' );

describe( 'bounded widget message catalog', () => {
	it( 'resolves the existing English labels through one catalog', () => {
		const { resolveWidgetMessage } = loadMessages();

		expect( resolveWidgetMessage( 'en-US', 'chat' ) ).toBe( 'Chat' );
		expect(
			resolveWidgetMessage( 'en-US', 'open_chat', {
				botName: 'Support Bot',
			} )
		).toBe( 'Open Support Bot chat' );
		expect( resolveWidgetMessage( 'en-US', 'close' ) ).toBe( 'Close' );
		expect( resolveWidgetMessage( 'en-US', 'message' ) ).toBe( 'Message' );
		expect( resolveWidgetMessage( 'en-US', 'send' ) ).toBe( 'Send' );
		expect( resolveWidgetMessage( 'en-US', 'retry' ) ).toBe( 'Retry' );
		expect( resolveWidgetMessage( 'en-US', 'copy' ) ).toBe( 'Copy' );
		expect( resolveWidgetMessage( 'en-US', 'sources' ) ).toBe( 'Sources' );
		expect( resolveWidgetMessage( 'en-US', 'sending' ) ).toBe(
			'Sending…'
		);
	} );

	it( 'supports one RTL locale path and falls back unsupported locales to English', () => {
		const { resolveWidgetMessage } = loadMessages();

		expect( resolveWidgetMessage( 'ur-PK', 'chat' ) ).toBe( 'چیٹ' );
		expect( resolveWidgetMessage( 'ur-PK', 'send' ) ).toBe( 'بھیجیں' );
		expect(
			resolveWidgetMessage( 'ur-PK', 'close_chat', {
				botName: 'مدد',
			} )
		).toBe( 'مدد چیٹ بند کریں' );
		expect( resolveWidgetMessage( 'fr-FR', 'send' ) ).toBe( 'Send' );
		expect( resolveWidgetMessage( undefined, 'retry' ) ).toBe( 'Retry' );
	} );

	it( 'returns plain text only for every bounded label', () => {
		const { resolveWidgetMessage } = loadMessages();
		const keys: WidgetMessageKey[] = [
			'chat',
			'open_chat',
			'chat_label',
			'close',
			'close_chat',
			'message',
			'send',
			'send_message',
			'retry',
			'copy',
			'copy_assistant_message',
			'sources',
			'assistant_typing',
			'sending',
			'rate_limited',
			'chat_unavailable',
			'send_failed',
		];

		for ( const locale of [ 'en-US', 'ur-PK', 'unsupported' ] ) {
			for ( const key of keys ) {
				const value = resolveWidgetMessage( locale, key, {
					botName: '<Support>',
				} );
				expect( value ).not.toMatch( /<[^>]*>/ );
			}
		}
	} );
} );
