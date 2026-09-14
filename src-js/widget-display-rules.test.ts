import { mountWidgets, type WidgetBootstrapConfig } from './widget-runtime';

declare const require: ( path: string ) => unknown;

const BOT_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

type WidgetConfigWindow = Window & {
	wpRagAiChatbotWidgetConfigs?: WidgetBootstrapConfig[];
};

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

const eligibleConfig = (): WidgetBootstrapConfig =>
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
	);

const loadWidget = (): void => {
	jest.resetModules();
	jest.isolateModules( () => {
		require( './widget' );
	} );
};

describe( 'public widget display rules', () => {
	beforeEach( () => {
		document.body.innerHTML = `<div class="wp-rag-ai-chatbot-widget" data-wp-rag-ai-chatbot-bot="${ BOT_ID }"></div>`;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete ( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs;
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
		expect( mountWidgets( document, [ eligibleConfig() ] ) ).toBe( 1 );
		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-launcher]' )
		).not.toBeNull();
	} );

	it( 'uses the shared runtime as the single visibility authority', () => {
		( window as WidgetConfigWindow ).wpRagAiChatbotWidgetConfigs = [
			eligibleConfig(),
		];

		loadWidget();

		expect(
			document.querySelector( '[data-wp-rag-ai-chatbot-launcher]' )
		).not.toBeNull();
	} );
} );
