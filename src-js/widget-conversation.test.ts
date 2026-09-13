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

const flushPromises = async (): Promise< void > => {
	for ( let index = 0; index < 5; index += 1 ) {
		await Promise.resolve();
	}
};

describe( 'public widget conversation controls', () => {
	beforeEach( () => {
		jest.useFakeTimers();
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
		jest.useRealTimers();
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

	it( 'renders successful text safely and reuses the returned conversation id', async () => {
		loadWidget();
		submitQuestion( '<strong>Hello?</strong>' );
		await flushPromises();
		jest.runAllTimers();

		const messages = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-messages]'
		);
		const userMessage = messages?.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="user"]'
		);
		const assistantMessage = messages?.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="assistant"]'
		);

		expect( userMessage?.textContent ).toBe( '<strong>Hello?</strong>' );
		expect( userMessage?.querySelector( 'strong' ) ).toBeNull();
		expect( assistantMessage?.textContent ).toBe( 'Hello' );

		submitQuestion( 'Follow up' );

		expect( fetchMock ).toHaveBeenCalledTimes( 2 );
		expect( fetchMock.mock.calls[ 1 ][ 1 ].body ).toBe(
			JSON.stringify( {
				bot_id: BOT_ID,
				question: 'Follow up',
				conversation_id: 'conversation-1',
			} )
		);
	} );

	it( 'accepts a successful response without a persisted conversation id', async () => {
		fetchMock.mockResolvedValue( {
			ok: true,
			json: async () => ( {
				answer: 'Stateless answer',
				conversation_id: null,
				citations: [],
			} ),
		} );
		loadWidget();
		submitQuestion( 'One-off question' );
		await flushPromises();
		jest.runAllTimers();

		const assistantMessage = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="assistant"]'
		);
		const retry = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-retry]'
		);

		expect( assistantMessage?.textContent ).toBe( 'Stateless answer' );
		expect( retry?.hidden ).toBe( true );

		submitQuestion( 'Second one-off question' );
		expect( fetchMock.mock.calls[ 1 ][ 1 ].body ).toBe(
			JSON.stringify( {
				bot_id: BOT_ID,
				question: 'Second one-off question',
			} )
		);
	} );
} );
