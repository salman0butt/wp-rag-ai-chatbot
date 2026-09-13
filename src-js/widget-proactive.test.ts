import {
	createProactiveDelayCoordinator,
	readProactiveDelayConfig,
} from './widget-proactive';
import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

const proactiveConfig = ( delayMs = 1000 ): WidgetBootstrapConfig =>
	( {
		botId: BOT_ID,
		restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
		config: {
			bot_id: BOT_ID,
			name: 'Support bot',
			appearance: {},
			display_rules: {
				enabled: true,
				proactive: {
					enabled: true,
					delay_ms: delayMs,
				},
			},
		},
	} ) as unknown as WidgetBootstrapConfig;

describe( 'M15 proactive widget delay', () => {
	let originalFetch: typeof globalThis.fetch;

	beforeEach( () => {
		jest.useFakeTimers();
		originalFetch = globalThis.fetch;
		globalThis.fetch = jest.fn() as unknown as typeof globalThis.fetch;
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		jest.useRealTimers();
		globalThis.fetch = originalFetch;
		document.body.innerHTML = '';
	} );

	it( 'opens the eligible floating panel after the configured delay without sending chat', () => {
		expect( mountWidgets( document, [ proactiveConfig() ] ) ).toBe( 1 );

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);

		expect( launcher?.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( panel?.hidden ).toBe( true );

		jest.advanceTimersByTime( 999 );
		expect( panel?.hidden ).toBe( true );

		jest.advanceTimersByTime( 1 );
		expect( launcher?.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( panel?.hidden ).toBe( false );
		expect( globalThis.fetch ).not.toHaveBeenCalled();
	} );

	it( 'does not reopen after the one automatic proactive open has fired', () => {
		mountWidgets( document, [ proactiveConfig( 250 ) ] );

		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);
		const close = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-close]'
		);

		jest.advanceTimersByTime( 250 );
		expect( panel?.hidden ).toBe( false );

		close?.click();
		expect( panel?.hidden ).toBe( true );

		jest.advanceTimersByTime( 1000 );
		expect( panel?.hidden ).toBe( true );
		expect( globalThis.fetch ).not.toHaveBeenCalled();
	} );

	it( 'cancels the pending automatic open after a manual launcher open', () => {
		mountWidgets( document, [ proactiveConfig( 500 ) ] );

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);
		const close = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-close]'
		);

		launcher?.click();
		close?.click();
		expect( panel?.hidden ).toBe( true );

		jest.advanceTimersByTime( 500 );
		expect( panel?.hidden ).toBe( true );
		expect( globalThis.fetch ).not.toHaveBeenCalled();
	} );

	it( 'clears a pending delay when the coordinator is disposed through cancel', () => {
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			readProactiveDelayConfig( {
				proactive: { enabled: true, delay_ms: 500 },
			} ),
			onOpen
		);

		coordinator.start();
		coordinator.cancel();
		jest.advanceTimersByTime( 500 );

		expect( onOpen ).not.toHaveBeenCalled();
	} );
} );
