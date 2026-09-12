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

const loadWidget = async (): Promise< void > => {
	jest.resetModules();
	await import( './widget' );
};

describe( 'public widget launcher and panel state', () => {
	beforeEach( () => {
		document.body.innerHTML =
			'<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"></div>';
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [ config ];
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete ( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs;
	} );

	it( 'mounts an accessible closed launcher and panel for matching bootstrap config', async () => {
		await loadWidget();

		const launcher = document.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-launcher]'
		);
		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);

		expect( launcher ).not.toBeNull();
		expect( launcher?.tagName ).toBe( 'BUTTON' );
		expect( launcher?.getAttribute( 'aria-label' ) ).toBe( 'Open Support bot chat' );
		expect( launcher?.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( panel ).not.toBeNull();
		expect( panel?.hidden ).toBe( true );
	} );
} );
