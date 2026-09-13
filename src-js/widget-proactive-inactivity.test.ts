import {
	createProactiveDelayCoordinator,
	readProactiveDelayConfig,
} from './widget-proactive';

describe( 'M15 proactive widget inactivity trigger', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.restoreAllMocks();
	} );

	it( 'normalizes the bounded inactivity duration from public display rules', () => {
		expect(
			readProactiveDelayConfig( {
				proactive: { enabled: true, inactivity_ms: 0 },
			} ).inactivityMs
		).toBe( 0 );
		expect(
			readProactiveDelayConfig( {
				proactive: { enabled: true, inactivity_ms: 600000 },
			} ).inactivityMs
		).toBe( 600000 );
		expect(
			readProactiveDelayConfig( {
				proactive: { enabled: true, inactivity_ms: 600001 },
			} ).inactivityMs
		).toBeNull();
	} );

	it( 'resets one inactivity timer on bounded visitor activity and opens once', () => {
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			readProactiveDelayConfig( {
				proactive: { enabled: true, inactivity_ms: 1000 },
			} ),
			onOpen
		);

		coordinator.start();
		jest.advanceTimersByTime( 900 );
		expect( onOpen ).not.toHaveBeenCalled();

		window.dispatchEvent( new Event( 'pointerdown' ) );
		jest.advanceTimersByTime( 900 );
		expect( onOpen ).not.toHaveBeenCalled();

		window.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Tab' } ) );
		jest.advanceTimersByTime( 999 );
		expect( onOpen ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( 1 );
		expect( onOpen ).toHaveBeenCalledTimes( 1 );

		window.dispatchEvent( new Event( 'pointerdown' ) );
		jest.advanceTimersByTime( 2000 );
		expect( onOpen ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'cancels pending inactivity work and removes its bounded activity listeners', () => {
		const removeEventListener = jest.spyOn( window, 'removeEventListener' );
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			readProactiveDelayConfig( {
				proactive: { enabled: true, inactivity_ms: 1000 },
			} ),
			onOpen
		);

		coordinator.start();
		coordinator.cancel();
		window.dispatchEvent( new Event( 'pointerdown' ) );
		window.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Tab' } ) );
		jest.advanceTimersByTime( 2000 );

		expect( onOpen ).not.toHaveBeenCalled();
		expect( removeEventListener ).toHaveBeenCalledWith(
			'pointerdown',
			expect.any( Function )
		);
		expect( removeEventListener ).toHaveBeenCalledWith(
			'keydown',
			expect.any( Function )
		);
	} );
} );
