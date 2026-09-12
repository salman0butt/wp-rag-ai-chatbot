export {};

declare const require: ( path: string ) => unknown;

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: unknown[];
};

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const originalFetch = globalThis.fetch;
let fetchMock: jest.Mock;

const flushPromises = async (): Promise< void > => {
	for ( let index = 0; index < 5; index += 1 ) {
		await Promise.resolve();
	}
};

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

describe( 'public widget conversation errors', () => {
	beforeEach( () => {
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			{
				botId: BOT_ID,
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				config: { bot_id: BOT_ID, name: 'Support bot', appearance: {} },
			},
		];
		fetchMock = jest.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( {
				code: 'rate_limited',
				message: 'provider secret detail must not render',
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

	it( 'maps public errors to safe copy and retries the same bounded request', async () => {
		loadWidget();
		submitQuestion( 'Help me' );
		await flushPromises();

		const status = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-status]'
		);
		const retry = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-retry]'
		);

		expect( status?.textContent ).toBe(
			'Too many requests. Please try again shortly.'
		);
		expect( document.body.textContent ).not.toContain(
			'provider secret detail'
		);
		expect( retry?.type ).toBe( 'button' );
		expect( retry?.textContent ).toBe( 'Retry' );

		retry?.click();

		expect( fetchMock ).toHaveBeenCalledTimes( 2 );
		expect( fetchMock.mock.calls[ 1 ][ 1 ].body ).toBe(
			JSON.stringify( { bot_id: BOT_ID, question: 'Help me' } )
		);
	} );
} );
