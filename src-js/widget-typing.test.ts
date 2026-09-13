export {};

declare const require: ( path: string ) => unknown;

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: unknown[];
};

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const originalFetch = globalThis.fetch;
let fetchMock: jest.Mock;

const answer =
	'This is a deliberately longer assistant answer that should be revealed progressively instead of appearing all at once.';

const flushPromises = async (): Promise< void > => {
	for ( let index = 0; index < 6; index += 1 ) {
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

describe( 'public widget simulated typing fallback', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			{
				botId: BOT_ID,
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				config: { bot_id: BOT_ID, name: 'Support bot', appearance: {} },
			},
		];
		fetchMock = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				ok: true,
				answer,
				conversation_id: 'conversation-1',
				citations: [
					{
						id: 'C1',
						title: 'Documentation',
						url: 'https://example.test/docs',
					},
				],
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

	it( 'progressively reveals a completed long answer before attaching completion controls', async () => {
		loadWidget();
		submitQuestion( 'Explain this' );
		await flushPromises();

		const assistant = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="assistant"]'
		);
		const status = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-status]'
		);

		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		expect( assistant ).not.toBeNull();
		expect( assistant?.textContent ).not.toBe( answer );
		expect( assistant?.getAttribute( 'aria-live' ) ).toBe( 'off' );
		expect( status?.textContent ).toBe( 'Assistant is typing…' );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-copy]' )
		).toBeNull();
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-sources]' )
		).toBeNull();

		jest.runAllTimers();

		expect( assistant?.textContent ).toBe( answer );
		expect( assistant?.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( status?.textContent ).toBe( '' );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-copy]' )
		).not.toBeNull();
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-sources]' )
		).not.toBeNull();
	} );

	it( 'never exposes a partial UTF-16 surrogate pair while revealing text', async () => {
		const unicodeAnswer = `A😀${ 'x'.repeat( 47 ) }`;
		fetchMock.mockResolvedValue( {
			ok: true,
			json: async () => ( {
				ok: true,
				answer: unicodeAnswer,
				conversation_id: 'conversation-1',
				citations: [],
			} ),
		} );

		loadWidget();
		submitQuestion( 'Show unicode' );
		await flushPromises();

		const assistant = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="assistant"]'
		);
		const partial = assistant?.textContent ?? '';

		expect( /[\uD800-\uDBFF]$/.test( partial ) ).toBe( false );

		jest.runAllTimers();
		expect( assistant?.textContent ).toBe( unicodeAnswer );
	} );

	it( 'cancels stale presentation work when the panel closes', async () => {
		loadWidget();

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const close = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-close]'
		);
		launcher?.click();
		submitQuestion( 'Explain cancellation' );
		await flushPromises();

		const assistant = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="assistant"]'
		);
		const status = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-status]'
		);
		const send = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-send]'
		);
		const partial = assistant?.textContent ?? '';

		expect( partial ).not.toBe( '' );
		expect( partial ).not.toBe( answer );
		close?.click();
		jest.runAllTimers();

		expect( assistant?.textContent ).toBe( partial );
		expect( status?.textContent ).toBe( '' );
		expect( send?.disabled ).toBe( false );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-copy]' )
		).toBeNull();
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-sources]' )
		).toBeNull();

		launcher?.click();
		jest.runAllTimers();

		expect( assistant?.textContent ).toBe( partial );
		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		expect(
			document.querySelectorAll(
				'[data-wp-rag-ai-chatbot-message-container="assistant"]'
			)
		).toHaveLength( 1 );
	} );
} );
