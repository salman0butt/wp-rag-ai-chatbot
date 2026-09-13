import type { WidgetBootstrapConfig } from './widget-runtime';

declare const require: ( path: string ) => unknown;

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

const config: WidgetBootstrapConfig = {
	botId: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
	restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
	surface: 'embedded',
	facts: { path: '/docs/guide' },
	config: {
		bot_id: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		name: 'Support bot',
		appearance: {},
		display_rules: {
			starters: {
				default: [ 'General help' ],
				by_page: [
					{
						pattern: '/docs/*',
						prompts: [
							'<strong>Docs</strong>',
							'Pricing',
							'Contact',
							'Fourth',
						],
					},
				],
			},
		},
	},
};

const loadWidget = (): void => {
	jest.resetModules();
	jest.isolateModules( () => {
		require( './widget' );
	} );
};

describe( 'M15 widget starter suggestions', () => {
	beforeEach( () => {
		document.body.innerHTML =
			'<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"></div>';
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [ config ];
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete ( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs;
	} );

	it( 'renders selected page starters as bounded native text buttons', () => {
		loadWidget();

		const starters = Array.from(
			document.querySelectorAll< HTMLButtonElement >(
				'[data-wp-rag-ai-chatbot-starter]'
			)
		);

		expect( starters ).toHaveLength( 4 );
		expect( starters.map( ( starter ) => starter.tagName ) ).toEqual( [
			'BUTTON',
			'BUTTON',
			'BUTTON',
			'BUTTON',
		] );
		expect( starters.map( ( starter ) => starter.textContent ) ).toEqual( [
			'<strong>Docs</strong>',
			'Pricing',
			'Contact',
			'Fourth',
		] );
		expect( starters[ 0 ]?.querySelector( 'strong' ) ).toBeNull();
	} );
} );
