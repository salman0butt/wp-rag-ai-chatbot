import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

const configWith = (
	displayRules: Record< string, unknown >,
	facts?: Record< string, unknown >
): WidgetBootstrapConfig =>
	( {
		botId: BOT_ID,
		restBase: 'https://example.test/wp-json/wp-rag-ai-chatbot/v1',
		config: {
			bot_id: BOT_ID,
			name: 'Support bot',
			appearance: {},
			display_rules: displayRules,
		},
		...( facts ? { facts } : {} ),
	} ) as unknown as WidgetBootstrapConfig;

describe( 'public widget display rules', () => {
	beforeEach( () => {
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'does not mount a widget when normalized display rules are disabled', () => {
		expect(
			mountWidgets( document, [ configWith( { enabled: false } ) ] )
		).toBe( 0 );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-launcher]' )
		).toBeNull();
	} );

	it( 'mounts an eligible widget using the trusted server presentation facts', () => {
		const configs = [
			configWith(
				{
					enabled: true,
					visibility: {
						include_paths: [ '/docs/*' ],
						authentication: 'authenticated',
						roles: [ 'editor' ],
						post_types: [ 'page' ],
						woo_areas: [ 'shop' ],
						schedule: {
							days: [ 1 ],
							start: '09:00',
							end: '17:00',
						},
					},
				},
				{
					path: '/docs/getting-started',
					isAuthenticated: true,
					roleMatches: [ 'editor' ],
					postType: 'page',
					wooArea: 'shop',
					siteWeekday: 1,
					siteMinuteOfDay: 10 * 60,
				}
			),
		];

		expect( mountWidgets( document, configs ) ).toBe( 1 );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-launcher]' )
		).not.toBeNull();
	} );
} );
