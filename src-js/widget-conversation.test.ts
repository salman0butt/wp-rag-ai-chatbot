export {};

declare const require: ( path: string ) => unknown;

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: Array< {
		botId: string;
		restBase: string;
		config: {
			bot_id: string;
			name: string;
			appearance: Record< string, unknown >;
		};
	} >;
};

const loadWidget = (): void => {
	jest.resetModules();
	jest.isolateModules( () => {
		require( './widget' );
	} );
};

describe( 'public widget conversation controls', () => {
	beforeEach( () => {
		document.body.innerHTML =
			'<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"></div>';
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			{
				botId: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				config: {
					bot_id: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
					name: 'Support bot',
					appearance: {},
				},
			},
		];
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete ( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs;
	} );

	it( 'mounts native accessible question and send controls inside the panel', () => {
		loadWidget();

		const panel = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-panel]'
		);
		const question = panel?.querySelector< HTMLTextAreaElement >(
			'[data-wp-rag-ai-chatbot-question]'
		);
		const send = panel?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-send]'
		);

		expect( question?.tagName ).toBe( 'TEXTAREA' );
		expect( question?.getAttribute( 'aria-label' ) ).toBe( 'Message' );
		expect( send?.tagName ).toBe( 'BUTTON' );
		expect( send?.type ).toBe( 'submit' );
		expect( send?.textContent ).toBe( 'Send' );
	} );
} );
