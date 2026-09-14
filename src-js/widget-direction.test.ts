import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

const configFor = (
	locale: string,
	direction: 'auto' | 'ltr' | 'rtl'
): WidgetBootstrapConfig => ( {
	botId: BOT_ID,
	restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
	config: {
		bot_id: BOT_ID,
		name: 'Direction Bot',
		appearance: {},
		display_rules: {
			enabled: true,
			localization: {
				locale,
				direction,
			},
		},
	},
} );

describe( 'widget-local language and direction', () => {
	beforeEach( () => {
		document.documentElement.lang = 'en';
		document.documentElement.dir = 'ltr';
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		document.documentElement.removeAttribute( 'lang' );
		document.documentElement.removeAttribute( 'dir' );
		document.body.innerHTML = '';
	} );

	it( 'applies the evaluator locale and inferred RTL direction to the widget root', () => {
		expect( mountWidgets( document, [ configFor( 'ur-pk', 'auto' ) ] ) ).toBe(
			1
		);

		const mount = document.querySelector< HTMLElement >(
			'.wp-rag-ai-chatbot-widget'
		);
		expect( mount?.getAttribute( 'lang' ) ).toBe( 'ur-pk' );
		expect( mount?.getAttribute( 'dir' ) ).toBe( 'rtl' );
	} );

	it( 'keeps an explicit widget direction independent from the document direction', () => {
		document.documentElement.dir = 'rtl';
		expect( mountWidgets( document, [ configFor( 'ur-pk', 'ltr' ) ] ) ).toBe(
			1
		);

		const mount = document.querySelector< HTMLElement >(
			'.wp-rag-ai-chatbot-widget'
		);
		expect( mount?.getAttribute( 'lang' ) ).toBe( 'ur-pk' );
		expect( mount?.getAttribute( 'dir' ) ).toBe( 'ltr' );
	} );
} );
