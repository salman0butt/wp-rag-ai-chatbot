export {};

declare const require: ( path: string ) => unknown;

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: Array< {
		botId: string;
		restBase: string;
		config: {
			bot_id: string;
			name: string;
			appearance: Record< string, unknown >;
		};
	} >;
};

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const originalFetch = globalThis.fetch;
let fetchMock: jest.Mock;

const loadWidget = (): void => {
	jest.resetModules();
	jest.isolateModules( () => {
		require( './widget' );
	} );
};

const submitQuestion = ( value: string ): void => {
	const form = document.querySelector< HTMLFormElement >(
		'[data-wp-rag-ai-chatbot-form]'
	);
	const question = document.querySelector< HTMLTextAreaElement >(
		'[data-wp-rag-ai-chatbot-question]'
	);

	if ( question ) {
		question.value = value;
	}
	form?.dispatchEvent(
		new Event( 'submit', { bubbles: true, cancelable: true } )
	);
};

describe( 'public widget conversation controls', () => {
	beforeEach( () => {
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			{
				botId: BOT_ID,
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				config: {
					bot_id: BOT_ID,
					name: 'Support bot',
					appearance: {},
				},
			},
		];
		fetchMock = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				answer: 'Hello',
				conversation_id: 'conversation-1',
				citations: [],
			} ),
		} );
		Object.defineProperty( globalThis, 'fetch', {
			value: fetchMock,
			writable: true,
			configurable: true,
		} );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete ( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs;
		Object.defineProperty( globalThis, 'fetch', {
			value: originalFetch,
			writable: true,
			configurable: true,
		} );
	} );

	it( 'mounts native accessible question and send controls inside the panel', () => {
		loadWidget();

		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);
		const question = panel?.querySelector< HTMLTextAreaElement >(
			'[data-wp-rag-ai-chatbot-question]'
		);
		const send = panel?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-send]'
		);

		expect( question?.tagName ).toBe( 'TEXTAREA' );
		expect( question?.getAttribute( 'aria-label' ) ).toBe( 'Message' );
		expect( send?.tagName ).toBe( 'BUTTON' );
		expect( send?.type ).toBe( 'submit' );
		expect( send?.textContent ).toBe( 'Send' );
	} );

	it( 'does not make a public chat request for whitespace-only input', () => {
		loadWidget();
		submitQuestion( '   ' );

		expect( fetchMock ).not.toHaveBeenCalled();
	} );

	it( 'submits only the closed public chat request fields', () => {
		loadWidget();
		submitQuestion( '  How can you help?  ' );

		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		expect( fetchMock ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/chat',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					bot_id: BOT_ID,
					question: 'How can you help?',
				} ),
			}
		);
	} );

	it( 'allows only one in-flight request and exposes a live loading state', () => {
		fetchMock.mockReturnValue( new Promise( () => undefined ) );
		loadWidget();

		submitQuestion( 'First question' );
		submitQuestion( 'Second question' );

		const send = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-send]'
		);
		const status = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-status]'
		);

		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		expect( send?.disabled ).toBe( true );
		expect( status?.getAttribute( 'role' ) ).toBe( 'status' );
		expect( status?.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( status?.textContent ).toBe( 'Sending…' );
	} );
} );
