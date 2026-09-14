import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

const localizedConfig = ( locale: string ): WidgetBootstrapConfig => ( {
	botId: BOT_ID,
	restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
	config: {
		bot_id: BOT_ID,
		name: 'مدد',
		appearance: {},
		display_rules: {
			enabled: true,
			localization: {
				locale,
				direction: 'auto',
			},
		},
	},
} );

describe( 'localized widget runtime labels', () => {
	beforeEach( () => {
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'renders Urdu control and accessible labels from the shared display-rule locale decision', () => {
		expect( mountWidgets( document, [ localizedConfig( 'ur-pk' ) ] ) ).toBe(
			1
		);

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);
		const close = panel?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-close]'
		);
		const question = panel?.querySelector< HTMLTextAreaElement >(
			'[data-wp-rag-ai-chatbot-question]'
		);
		const send = panel?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-send]'
		);
		const retry = panel?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-retry]'
		);

		expect( launcher?.textContent ).toBe( 'چیٹ' );
		expect( launcher?.getAttribute( 'aria-label' ) ).toBe(
			'مدد چیٹ کھولیں'
		);
		expect( panel?.getAttribute( 'aria-label' ) ).toBe( 'مدد چیٹ' );
		expect( close?.textContent ).toBe( 'بند کریں' );
		expect( close?.getAttribute( 'aria-label' ) ).toBe(
			'مدد چیٹ بند کریں'
		);
		expect( question?.getAttribute( 'aria-label' ) ).toBe( 'پیغام' );
		expect( send?.textContent ).toBe( 'بھیجیں' );
		expect( send?.getAttribute( 'aria-label' ) ).toBe( 'پیغام بھیجیں' );
		expect( retry?.textContent ).toBe( 'دوبارہ کوشش کریں' );
	} );

	it( 'keeps unsupported runtime locales on the English fallback', () => {
		expect( mountWidgets( document, [ localizedConfig( 'fr-fr' ) ] ) ).toBe(
			1
		);

		expect(
			document.querySelector< HTMLButtonElement >(
				'[data-wp-rag-ai-chatbot-launcher]'
			)?.textContent
		).toBe( 'Chat' );
		expect(
			document.querySelector< HTMLButtonElement >(
				'[data-wp-rag-ai-chatbot-send]'
			)?.textContent
		).toBe( 'Send' );
	} );
} );
