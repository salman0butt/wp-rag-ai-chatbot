import { mountWidgets } from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

const appearance = {
	primary_color: '#1d4ed8',
	color_mode: 'light',
	position: 'bottom-right',
	launcher_style: 'bubble',
	panel_size: 'medium',
	radius_px: 16,
	font_family: 'system',
};

const mountSurface = ( surface: 'embedded' | 'fullscreen' ): HTMLElement => {
	document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }" data-wp-rag-ai-chatbot-surface="${ surface }"></div>`;

	const config = {
		botId: BOT_ID,
		restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
		surface,
		config: {
			bot_id: BOT_ID,
			name: 'Support bot',
			appearance,
		},
	};

	mountWidgets( document, [ config ] );

	const mount = document.querySelector< HTMLElement >(
		'.wp-rag-ai-chatbot-widget'
	);
	if ( mount === null ) {
		throw new Error( 'Expected widget mount.' );
	}

	return mount;
};

describe( 'public widget embedding surfaces', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it.each( [ 'embedded', 'fullscreen' ] as const )(
		'renders the %s surface open without a floating launcher',
		( surface ) => {
			const mount = mountSurface( surface );
			const launcher = mount.querySelector(
				'[data-wp-rag-ai-chatbot-launcher]'
			);
			const panel = mount.querySelector< HTMLElement >(
				'[data-wp-rag-ai-chatbot-panel]'
			);

			expect( launcher ).toBeNull();
			expect( panel ).not.toBeNull();
			expect( panel?.hidden ).toBe( false );
			expect( mount.dataset.wpRagAiChatbotSurface ).toBe( surface );
		}
	);
} );
