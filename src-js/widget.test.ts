export {};

declare const require: ( path: string ) => unknown;

type WidgetBootstrapConfig = {
	botId: string;
	restBase: string;
	config: {
		bot_id: string;
		name: string;
		appearance: {
			primary_color: string;
			color_mode: string;
			position: string;
			launcher_style: string;
			panel_size: string;
			radius_px: number;
			font_family: string;
		};
	};
};

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

const config: WidgetBootstrapConfig = {
	botId: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
	restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
	config: {
		bot_id: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
		name: 'Support bot',
		appearance: {
			primary_color: '#1d4ed8',
			color_mode: 'light',
			position: 'bottom-right',
			launcher_style: 'bubble',
			panel_size: 'medium',
			radius_px: 16,
			font_family: 'system',
		},
	},
};

const loadWidget = (): void => {
	jest.resetModules();
	jest.isolateModules( () => {
		require( './widget' );
	} );
};

describe( 'public widget launcher and panel state', () => {
	beforeEach( () => {
		document.body.innerHTML =
			'<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"></div>';
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			config,
		];
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete ( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs;
	} );

	it( 'mounts an accessible closed launcher and panel for matching bootstrap config', () => {
		loadWidget();

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);

		expect( launcher ).not.toBeNull();
		expect( launcher?.tagName ).toBe( 'BUTTON' );
		expect( launcher?.getAttribute( 'aria-label' ) ).toBe(
			'Open Support bot chat'
		);
		expect( launcher?.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( panel ).not.toBeNull();
		expect( panel?.hidden ).toBe( true );
	} );

	it( 'opens the panel with an accessible close control and moves focus into it', () => {
		loadWidget();

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);

		launcher?.click();

		const close = panel?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-close]'
		);

		expect( launcher?.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( panel?.hidden ).toBe( false );
		expect( close ).not.toBeNull();
		expect( close?.tagName ).toBe( 'BUTTON' );
		expect( close?.getAttribute( 'aria-label' ) ).toBe(
			'Close Support bot chat'
		);
		expect( close?.ownerDocument.activeElement ).toBe( close );
	} );

	it( 'closes the panel and restores focus to the launcher', () => {
		loadWidget();

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);

		launcher?.click();
		const close = panel?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-close]'
		);
		close?.click();

		expect( launcher?.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( panel?.hidden ).toBe( true );
		expect( launcher?.ownerDocument.activeElement ).toBe( launcher );
	} );
} );
