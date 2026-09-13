import type { WidgetBootstrapConfig } from './widget-runtime';

declare const require: ( path: string ) => unknown;

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const originalFetch = globalThis.fetch;
let fetchMock: jest.Mock;

const config: WidgetBootstrapConfig = {
	botId: BOT_ID,
	restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
	surface: 'embedded',
	facts: { path: '/docs/guide' },
	config: {
		bot_id: BOT_ID,
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

const flushPromises = async (): Promise< void > => {
	for ( let index = 0; index < 5; index += 1 ) {
		await Promise.resolve();
	}
};

describe( 'M15 widget starter suggestions', () => {
	beforeEach( () => {
		document.body.innerHTML =
			`<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			config,
		];
		fetchMock = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				answer: 'Starter answer',
				conversation_id: 'conversation-1',
				citations: [],
			} ),
		} );
		Object.defineProperty( globalThis, 'fetch', {
			value: fetchMock,
			writable: true,
			configurable: true,
		} );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete ( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs;
		Object.defineProperty( globalThis, 'fetch', {
			value: originalFetch,
			writable: true,
			configurable: true,
		} );
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

	it( 'submits a selected starter exactly once through the shared chat form', async () => {
		loadWidget();

		const starters = Array.from(
			document.querySelectorAll< HTMLButtonElement >(
				'[data-wp-rag-ai-chatbot-starter]'
			)
		);
		starters[ 1 ]?.click();
		await flushPromises();

		expect( fetchMock ).toHaveBeenCalledTimes( 1 );
		expect( fetchMock ).toHaveBeenCalledWith(
			'https://example.test/wp-json/wp-rag-ai-chatbot/v1/chat',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					bot_id: BOT_ID,
					question: 'Pricing',
				} ),
			}
		);
	} );
} );
