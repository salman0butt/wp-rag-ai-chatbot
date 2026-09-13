import {
	createProactiveDelayCoordinator,
	readProactiveDelayConfig,
} from './widget-proactive';

const setScrollState = (
	scrollY: number,
	scrollHeight = 1500,
	clientHeight = 500
): void => {
	Object.defineProperty( window, 'scrollY', {
		configurable: true,
		value: scrollY,
	} );
	Object.defineProperty( document.documentElement, 'scrollHeight', {
		configurable: true,
		value: scrollHeight,
	} );
	Object.defineProperty( document.documentElement, 'clientHeight', {
		configurable: true,
		value: clientHeight,
	} );
};

describe( 'M15 proactive scroll lifecycle hardening', () => {
	it( 'rejects scroll thresholds below the persisted one-percent minimum', () => {
		expect(
			readProactiveDelayConfig( {
				proactive: { enabled: true, scroll_percent: 0 },
			} ).scrollPercent
		).toBeNull();
		expect(
			readProactiveDelayConfig( {
				proactive: { enabled: true, scroll_percent: 1 },
			} ).scrollPercent
		).toBe( 1 );
	} );

	it( 'coalesces burst scroll events through one animation-frame evaluation', () => {
		const callbacks: FrameRequestCallback[] = [];
		const requestAnimationFrame = jest
			.spyOn( window, 'requestAnimationFrame' )
			.mockImplementation( ( callback: FrameRequestCallback ) => {
				callbacks.push( callback );
				return callbacks.length;
			} );
		const cancelAnimationFrame = jest
			.spyOn( window, 'cancelAnimationFrame' )
			.mockImplementation( () => undefined );
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			readProactiveDelayConfig( {
				proactive: { enabled: true, scroll_percent: 50 },
			} ),
			onOpen
		);

		setScrollState( 0 );
		coordinator.start();
		setScrollState( 500 );
		window.dispatchEvent( new Event( 'scroll' ) );
		window.dispatchEvent( new Event( 'scroll' ) );

		expect( onOpen ).not.toHaveBeenCalled();
		expect( callbacks ).toHaveLength( 1 );

		callbacks[ 0 ]( 0 );
		expect( onOpen ).toHaveBeenCalledTimes( 1 );

		coordinator.cancel();
		requestAnimationFrame.mockRestore();
		cancelAnimationFrame.mockRestore();
	} );

	it( 'does not treat a non-scrollable page as one hundred percent scrolled', () => {
		const onOpen = jest.fn();
		const coordinator = createProactiveDelayCoordinator(
			readProactiveDelayConfig( {
				proactive: { enabled: true, scroll_percent: 50 },
			} ),
			onOpen
		);

		setScrollState( 0, 500, 500 );
		coordinator.start();

		expect( onOpen ).not.toHaveBeenCalled();
		coordinator.cancel();
	} );
} );
