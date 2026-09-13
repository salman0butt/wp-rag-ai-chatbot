import {
	createProactiveDelayCoordinator,
	readProactiveDelayConfig,
	type ProactiveDelayCoordinator,
} from './widget-proactive';

type ExtendedProactiveConfig = ReturnType< typeof readProactiveDelayConfig > & {
	firstVisitOnly: boolean;
	clickSelector: string | null;
};

type CoordinatorContext = {
	botId: string;
	documentRoot: Document;
};

type CoordinatorFactory = (
	config: ExtendedProactiveConfig,
	onOpen: () => void,
	context: CoordinatorContext
) => ProactiveDelayCoordinator;

const createCoordinator =
	createProactiveDelayCoordinator as unknown as CoordinatorFactory;

const readExtendedConfig = ( displayRules: unknown ): ExtendedProactiveConfig =>
	readProactiveDelayConfig( displayRules ) as ExtendedProactiveConfig;

const click = ( element: Element ): void => {
	element.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
};

describe( 'M15 proactive click and first-visit triggers', () => {
	beforeEach( () => {
		document.body.innerHTML = '';
		window.localStorage.clear();
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	it( 'normalizes only bounded safe click selectors and the first-visit flag', () => {
		const valid = readExtendedConfig( {
			proactive: {
				enabled: true,
				first_visit_only: true,
				click_selector: '.sales-cta',
			},
		} );
		const unsafe = readExtendedConfig( {
			proactive: {
				enabled: true,
				first_visit_only: 'true',
				click_selector: 'main > .sales-cta',
			},
		} );

		expect( valid.firstVisitOnly ).toBe( true );
		expect( valid.clickSelector ).toBe( '.sales-cta' );
		expect( unsafe.firstVisitOnly ).toBe( false );
		expect( unsafe.clickSelector ).toBeNull();
	} );

	it( 'uses delegated matching so a descendant click can satisfy the selector once', () => {
		document.body.innerHTML =
			'<button class="sales-cta"><span data-child>Talk to sales</span></button>';
		const onOpen = jest.fn();
		const coordinator = createCoordinator(
			readExtendedConfig( {
				proactive: {
					enabled: true,
					click_selector: '.sales-cta',
				},
			} ),
			onOpen,
			{ botId: 'bot-click-delegated', documentRoot: document }
		);

		coordinator.start();
		const child = document.querySelector( '[data-child]' );
		expect( child ).not.toBeNull();
		click( child as Element );
		click( child as Element );

		expect( onOpen ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'stores a bot-scoped first-visit marker and suppresses later coordinators for that bot', () => {
		document.body.innerHTML = '<button class="welcome-cta">Welcome</button>';
		const config = readExtendedConfig( {
			proactive: {
				enabled: true,
				first_visit_only: true,
				click_selector: '.welcome-cta',
			},
		} );
		const firstOpen = jest.fn();
		const secondOpen = jest.fn();
		const first = createCoordinator( config, firstOpen, {
			botId: 'bot-first-visit-storage',
			documentRoot: document,
		} );

		first.start();
		expect(
			window.localStorage.getItem(
				'wp-rag-ai-chatbot:proactive-seen:bot-first-visit-storage'
			)
		).toBe( '1' );
		const target = document.querySelector( '.welcome-cta' ) as Element;
		click( target );
		expect( firstOpen ).toHaveBeenCalledTimes( 1 );

		const second = createCoordinator( config, secondOpen, {
			botId: 'bot-first-visit-storage',
			documentRoot: document,
		} );
		second.start();
		click( target );
		expect( secondOpen ).not.toHaveBeenCalled();
	} );

	it( 'falls back to session-local bot scoping when storage throws without cross-bot bleed', () => {
		document.body.innerHTML = '<button id="fallback-cta">Open</button>';
		jest.spyOn( Storage.prototype, 'getItem' ).mockImplementation( () => {
			throw new Error( 'storage unavailable' );
		} );
		jest.spyOn( Storage.prototype, 'setItem' ).mockImplementation( () => {
			throw new Error( 'storage unavailable' );
		} );
		const config = readExtendedConfig( {
			proactive: {
				enabled: true,
				first_visit_only: true,
				click_selector: '#fallback-cta',
			},
		} );
		const target = document.querySelector( '#fallback-cta' ) as Element;
		const firstOpen = jest.fn();
		const sameBotOpen = jest.fn();
		const otherBotOpen = jest.fn();

		const first = createCoordinator( config, firstOpen, {
			botId: 'bot-storage-fallback-a',
			documentRoot: document,
		} );
		first.start();
		click( target );
		expect( firstOpen ).toHaveBeenCalledTimes( 1 );

		const sameBot = createCoordinator( config, sameBotOpen, {
			botId: 'bot-storage-fallback-a',
			documentRoot: document,
		} );
		sameBot.start();
		click( target );
		expect( sameBotOpen ).not.toHaveBeenCalled();

		const otherBot = createCoordinator( config, otherBotOpen, {
			botId: 'bot-storage-fallback-b',
			documentRoot: document,
		} );
		otherBot.start();
		click( target );
		expect( otherBotOpen ).toHaveBeenCalledTimes( 1 );
	} );
} );
