export {};

declare const require: ( path: string ) => unknown;

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: unknown[];
};

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const originalFetch = globalThis.fetch;
const originalClipboard = navigator.clipboard;
let fetchMock: jest.Mock;
let writeText: jest.Mock;

const flushPromises = async (): Promise< void > => {
	for ( let index = 0; index < 6; index += 1 ) {
		await Promise.resolve();
	}
};

const loadWidget = (): void => {
	jest.resetModules();
	jest.isolateModules( () => {
		require( './widget' );
	} );
};

const submitQuestion = ( value: string ): void => {
	const form = document.querySelector< HTMLFormElement >(
		'[data-wp-rag-ai-chatbot-form]'
	);
	const question = document.querySelector< HTMLTextAreaElement >(
		'[data-wp-rag-ai-chatbot-question]'
	);

	if ( question ) {
		question.value = value;
	}
	form?.dispatchEvent(
		new Event( 'submit', { bubbles: true, cancelable: true } )
	);
};

describe( 'public widget message presentation', () => {
	beforeEach( () => {
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			{
				botId: BOT_ID,
				restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
				config: { bot_id: BOT_ID, name: 'Support bot', appearance: {} },
			},
		];
		fetchMock = jest.fn().mockResolvedValue( {
			ok: true,
			json: async () => ( {
				ok: true,
				answer: '<strong>Read the sources</strong>',
				conversation_id: 'conversation-1',
				citations: [
					{
						id: 'safe',
						title: 'Safe <em>Docs</em>',
						url: 'https://example.test/docs',
					},
					{
						id: 'unsafe',
						title: 'Unsafe source',
						url: 'javascript:alert(1)',
					},
				],
			} ),
		} );
		Object.defineProperty( globalThis, 'fetch', {
			value: fetchMock,
			writable: true,
			configurable: true,
		} );
		writeText = jest.fn().mockResolvedValue( undefined );
		Object.defineProperty( navigator, 'clipboard', {
			value: { writeText },
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
		Object.defineProperty( navigator, 'clipboard', {
			value: originalClipboard,
			configurable: true,
		} );
	} );

	it( 'renders assistant text, copy, and citations without trusting HTML or unsafe URLs', async () => {
		loadWidget();
		submitQuestion( 'Show sources' );
		await flushPromises();

		const assistant = document.querySelector< HTMLElement >(
			'[data-wp-rag-ai-chatbot-message="assistant"]'
		);
		const wrapper = assistant?.parentElement;
		const copy = wrapper?.querySelector< HTMLButtonElement >(
			'[data-wp-rag-ai-chatbot-copy]'
		);
		const sources = wrapper?.querySelector< HTMLDetailsElement >(
			'[data-wp-rag-ai-chatbot-sources]'
		);
		const links = sources?.querySelectorAll< HTMLAnchorElement >( 'a' );

		expect( assistant?.textContent ).toContain(
			'<strong>Read the sources</strong>'
		);
		expect( assistant?.querySelector( 'strong' ) ).toBeNull();
		expect( copy?.getAttribute( 'aria-label' ) ).toBe(
			'Copy assistant message'
		);
		copy?.click();
		expect( writeText ).toHaveBeenCalledWith(
			'<strong>Read the sources</strong>'
		);
		expect( sources?.querySelector( 'summary' )?.textContent ).toBe(
			'Sources'
		);
		expect( links ).toHaveLength( 1 );
		expect( links?.[ 0 ].href ).toBe( 'https://example.test/docs' );
		expect( links?.[ 0 ].target ).toBe( '_blank' );
		expect( links?.[ 0 ].rel ).toBe( 'noopener noreferrer' );
		expect( links?.[ 0 ].textContent ).toBe( 'Safe <em>Docs</em>' );
		expect( links?.[ 0 ].querySelector( 'em' ) ).toBeNull();
		expect( document.body.textContent ).toContain( 'Unsafe source' );
		expect( sources?.querySelector( 'a[href^="javascript:"]' ) ).toBeNull();
	} );
} );
