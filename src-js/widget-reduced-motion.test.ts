import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const answer =
	'This assistant answer should render immediately when reduced motion is requested.';

const flushPromises = async (): Promise< void > => {
	for ( let index = 0; index < 6; index += 1 ) {
		await Promise.resolve();
	}
};

describe( 'widget reduced-motion presentation', () => {
	let originalFetch: typeof globalThis.fetch;
	let originalMatchMedia: typeof window.matchMedia;

	beforeEach( () => {
		jest.useFakeTimers();
		originalFetch = globalThis.fetch;
		originalMatchMedia = window.matchMedia;
		globalThis.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				answer,
				conversation_id: 'conversation-1',
				citations: [],
			} ),
		} ) as unknown as typeof globalThis.fetch;
		Object.defineProperty( window, 'matchMedia', {
			configurable: true,
			value: jest.fn( ( query: string ) => ( {
				matches: query === '(prefers-reduced-motion: reduce)',
				media: query,
				onchange: null,
				addEventListener: jest.fn(),
				removeEventListener: jest.fn(),
				addListener: jest.fn(),
				removeListener: jest.fn(),
				dispatchEvent: jest.fn(),
			} ) ),
		} );
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		jest.useRealTimers();
		globalThis.fetch = originalFetch;
		Object.defineProperty( window, 'matchMedia', {
			configurable: true,
			value: originalMatchMedia,
		} );
		document.body.innerHTML = '';
	} );

	it( 'skips simulated typing and exposes the completed answer immediately', async () => {
		const config: WidgetBootstrapConfig = {
			botId: BOT_ID,
			restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
			config: {
				bot_id: BOT_ID,
				name: 'Support bot',
				appearance: {},
			},
		};
		expect( mountWidgets( document, [ config ] ) ).toBe( 1 );

		const question = document.querySelector< HTMLTextAreaElement >(
			'[data-wp-rag-ai-chatbot-question]'
		);
		const form = document.querySelector< HTMLFormElement >(
			'[data-wp-rag-ai-chatbot-form]'
		);
		question!.value = 'Explain this';
		form!.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		await flushPromises();

		const assistant = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="assistant"]'
		);
		const status = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-status]'
		);

		expect( assistant?.textContent ).toBe( answer );
		expect( assistant?.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( status?.textContent ).toBe( '' );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-copy]' )
		).not.toBeNull();
		expect( jest.getTimerCount() ).toBe( 0 );
	} );
} );
