import {
	mountWidgets,
	type WidgetBootstrapConfig,
} from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

describe( 'public widget display rules', () => {
	beforeEach( () => {
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'does not mount a widget when normalized display rules are disabled', () => {
		const configs = [
			{
				botId: BOT_ID,
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				config: {
					bot_id: BOT_ID,
					name: 'Support bot',
					appearance: {},
					display_rules: { enabled: false },
				},
			},
		] as unknown as WidgetBootstrapConfig[];

		expect( mountWidgets( document, configs ) ).toBe( 0 );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-launcher]' )
		).toBeNull();
	} );
} );
