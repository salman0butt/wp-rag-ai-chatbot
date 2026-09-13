import {
	createProactiveDelayCoordinator,
	readProactiveDelayConfig,
} from './widget-proactive';

const readExitIntent = ( displayRules: unknown ): boolean | undefined =>
	(
		readProactiveDelayConfig( displayRules ) as {
			exitIntent?: boolean;
		}
	 ).exitIntent;

type ExitIntentConfig = ReturnType< typeof readProactiveDelayConfig > & {
	exitIntent: boolean;
};

const withExitIntent = ( displayRules: unknown ): ExitIntentConfig => {
	return {
		...readProactiveDelayConfig( displayRules ),
		exitIntent: true,
	};
};

const setFinePointer = ( matches: boolean ): void => {
	Object.defineProperty( window, 'matchMedia', {
		configurable: true,
		writable: true,
		value: jest.fn().mockImplementation( ( query: string ) => ( {
			matches,
			media: query,
			onchange: null,
			addListener: jest.fn(),
			removeListener: jest.fn(),
			addEventListener: jest.fn(),
			removeEventListener: jest.fn(),
			dispatchEvent: jest.fn(),
		} ) ),
	} );
};

describe( 'M15 proactive widget exit-intent trigger', () => {
	const originalMatchMedia = window.matchMedia;

	afterEach( () => {
		Object.defineProperty( window, 'matchMedia', {
			configurable: true,
			writable: true,
			value: originalMatchMedia,
		} );
		jest.restoreAllMocks();
	} );

	it( 'normalizes the persisted exit-intent flag from public display rules', () => {
		expect(
			readExitIntent( {
				proactive: { enabled: true, exit_intent: true },
			} )
		).toBe( true );
		expect(
			readExitIntent( {
				proactive: { enabled: true, exit_intent: false },
			} )
		).toBe( false );
		expect(
			readExitIntent( {
				proactive: { enabled: true, exit_intent: 'true' },
			} )
		).toBe( false );
	} );

	it( 'opens once for top-boundary exit intent on a fine pointer', () => {
		setFinePointer( true );
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			withExitIntent( {
				proactive: { enabled: true, exit_intent: true },
			} ),
			onOpen
		);

		coordinator.start();
		window.dispatchEvent(
			new MouseEvent( 'mouseout', { clientY: 20, relatedTarget: null } )
		);
		expect( onOpen ).not.toHaveBeenCalled();

		window.dispatchEvent(
			new MouseEvent( 'mouseout', { clientY: 0, relatedTarget: null } )
		);
		expect( onOpen ).toHaveBeenCalledTimes( 1 );

		window.dispatchEvent(
			new MouseEvent( 'mouseout', { clientY: 0, relatedTarget: null } )
		);
		expect( onOpen ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not treat keyboard activity or coarse pointers as exit intent', () => {
		setFinePointer( false );
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			withExitIntent( {
				proactive: { enabled: true, exit_intent: true },
			} ),
			onOpen
		);

		coordinator.start();
		window.dispatchEvent(
			new KeyboardEvent( 'keydown', { key: 'Escape' } )
		);
		window.dispatchEvent(
			new MouseEvent( 'mouseout', { clientY: 0, relatedTarget: null } )
		);

		expect( onOpen ).not.toHaveBeenCalled();
	} );

	it( 'removes exit-intent work when the coordinator is cancelled', () => {
		setFinePointer( true );
		const removeEventListener = jest.spyOn( window, 'removeEventListener' );
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			withExitIntent( {
				proactive: { enabled: true, exit_intent: true },
			} ),
			onOpen
		);

		coordinator.start();
		coordinator.cancel();
		window.dispatchEvent(
			new MouseEvent( 'mouseout', { clientY: 0, relatedTarget: null } )
		);

		expect( onOpen ).not.toHaveBeenCalled();
		expect( removeEventListener ).toHaveBeenCalledWith(
			'mouseout',
			expect.any( Function )
		);
	} );
} );
