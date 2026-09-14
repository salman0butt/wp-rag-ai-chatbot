import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

const config: WidgetBootstrapConfig = {
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
				delay_ms: 100,
			},
		},
	},
};

describe( 'M15 widget focus lifecycle', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		document.body.innerHTML = `<button id="page-action">Page action</button><div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		jest.useRealTimers();
		document.body.innerHTML = '';
	} );

	it( 'opens proactively without stealing page focus', () => {
		const pageAction = document.querySelector< HTMLButtonElement >(
			'#page-action'
		);
		pageAction?.focus();

		expect( mountWidgets( document, [ config ] ) ).toBe( 1 );
		jest.advanceTimersByTime( 100 );

		expect(
			document.querySelector< HTMLElement >(
				'[data-wp-rag-ai-chatbot-panel]'
			)?.hidden
		).toBe( false );
		expect( document.activeElement ).toBe( pageAction );
	} );

	it( 'moves focus on manual open and returns it to the launcher on Escape', () => {
		expect( mountWidgets( document, [ config ] ) ).toBe( 1 );
		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const close = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-close]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);

		launcher?.click();
		expect( document.activeElement ).toBe( close );

		panel?.dispatchEvent(
			new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } )
		);
		expect( panel?.hidden ).toBe( true );
		expect( launcher?.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( document.activeElement ).toBe( launcher );

		jest.advanceTimersByTime( 100 );
		expect( panel?.hidden ).toBe( true );
	} );
} );
