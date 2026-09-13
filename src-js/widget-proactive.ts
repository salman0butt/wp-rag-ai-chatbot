type ProactiveDelayConfig = {
	enabled: boolean;
	delayMs: number | null;
	scrollPercent: number | null;
	inactivityMs: number | null;
	exitIntent: boolean;
};

export type ProactiveDelayCoordinator = {
	start: () => void;
	cancel: () => void;
};

const MAX_TIMER_MS = 600000;
const MAX_SCROLL_PERCENT = 100;
const INACTIVITY_EVENTS = [ 'pointerdown', 'keydown' ] as const;
const FINE_POINTER_QUERY = '(hover: hover) and (pointer: fine)';

const asRecord = ( value: unknown ): Record< string, unknown > =>
	typeof value === 'object' && value !== null
		? ( value as Record< string, unknown > )
		: {};

const normalizeDelay = ( value: unknown ): number | null =>
	typeof value === 'number' &&
	Number.isInteger( value ) &&
	value >= 0 &&
	value <= MAX_TIMER_MS
		? value
		: null;

const normalizeScrollPercent = ( value: unknown ): number | null =>
	typeof value === 'number' &&
	Number.isInteger( value ) &&
	value >= 1 &&
	value <= MAX_SCROLL_PERCENT
		? value
		: null;

export const readProactiveDelayConfig = (
	displayRules: unknown
): ProactiveDelayConfig => {
	const rules = asRecord( displayRules );
	const proactive = asRecord( rules.proactive );

	return {
		enabled: proactive.enabled === true,
		delayMs: normalizeDelay( proactive.delay_ms ),
		scrollPercent: normalizeScrollPercent( proactive.scroll_percent ),
		inactivityMs: normalizeDelay( proactive.inactivity_ms ),
		exitIntent: proactive.exit_intent === true,
	};
};

export const createProactiveDelayCoordinator = (
	config: ProactiveDelayConfig,
	onOpen: () => void
): ProactiveDelayCoordinator => {
	let timer: ReturnType< typeof setTimeout > | null = null;
	let inactivityTimer: ReturnType< typeof setTimeout > | null = null;
	let scrollFrame: number | null = null;
	let listeningForScroll = false;
	let listeningForActivity = false;
	let listeningForExitIntent = false;
	let completed = false;

	const stopScrollListener = (): void => {
		if ( scrollFrame !== null ) {
			window.cancelAnimationFrame( scrollFrame );
			scrollFrame = null;
		}
		if ( listeningForScroll ) {
			window.removeEventListener( 'scroll', scheduleScrollEvaluation );
			listeningForScroll = false;
		}
	};

	const stopInactivityListener = (): void => {
		if ( inactivityTimer !== null ) {
			clearTimeout( inactivityTimer );
			inactivityTimer = null;
		}
		if ( listeningForActivity ) {
			INACTIVITY_EVENTS.forEach( ( eventName ) => {
				window.removeEventListener( eventName, resetInactivityTimer );
			} );
			listeningForActivity = false;
		}
	};

	const stopExitIntentListener = (): void => {
		if ( listeningForExitIntent ) {
			window.removeEventListener( 'mouseout', handleExitIntent );
			listeningForExitIntent = false;
		}
	};

	const complete = (): void => {
		if ( completed ) {
			return;
		}

		completed = true;
		if ( timer !== null ) {
			clearTimeout( timer );
			timer = null;
		}
		stopScrollListener();
		stopInactivityListener();
		stopExitIntentListener();
		onOpen();
	};

	const evaluateScroll = (): void => {
		if ( completed || config.scrollPercent === null ) {
			return;
		}

		const root = document.documentElement;
		const scrollableDistance = Math.max(
			0,
			root.scrollHeight - root.clientHeight
		);
		if ( scrollableDistance === 0 ) {
			return;
		}

		const percent =
			( window.scrollY / scrollableDistance ) * MAX_SCROLL_PERCENT;

		if ( percent >= config.scrollPercent ) {
			complete();
		}
	};

	function scheduleScrollEvaluation(): void {
		if (
			completed ||
			config.scrollPercent === null ||
			scrollFrame !== null
		) {
			return;
		}

		scrollFrame = window.requestAnimationFrame( () => {
			scrollFrame = null;
			evaluateScroll();
		} );
	}

	function resetInactivityTimer(): void {
		if ( completed || config.inactivityMs === null ) {
			return;
		}

		if ( inactivityTimer !== null ) {
			clearTimeout( inactivityTimer );
		}
		inactivityTimer = setTimeout( () => {
			inactivityTimer = null;
			complete();
		}, config.inactivityMs );
	}

	function handleExitIntent( event: MouseEvent ): void {
		if (
			completed ||
			! config.exitIntent ||
			event.relatedTarget !== null ||
			event.clientY > 0
		) {
			return;
		}

		complete();
	}

	const cancel = (): void => {
		completed = true;
		if ( timer !== null ) {
			clearTimeout( timer );
			timer = null;
		}
		stopScrollListener();
		stopInactivityListener();
		stopExitIntentListener();
	};

	const start = (): void => {
		if ( completed || ! config.enabled ) {
			return;
		}

		if ( timer === null && config.delayMs !== null ) {
			timer = setTimeout( () => {
				timer = null;
				complete();
			}, config.delayMs );
		}

		if ( ! listeningForScroll && config.scrollPercent !== null ) {
			listeningForScroll = true;
			window.addEventListener( 'scroll', scheduleScrollEvaluation, {
				passive: true,
			} );
			scheduleScrollEvaluation();
		}

		if ( ! listeningForActivity && config.inactivityMs !== null ) {
			listeningForActivity = true;
			INACTIVITY_EVENTS.forEach( ( eventName ) => {
				window.addEventListener( eventName, resetInactivityTimer );
			} );
			resetInactivityTimer();
		}

		if (
			! listeningForExitIntent &&
			config.exitIntent &&
			typeof window.matchMedia === 'function' &&
			window.matchMedia( FINE_POINTER_QUERY ).matches
		) {
			listeningForExitIntent = true;
			window.addEventListener( 'mouseout', handleExitIntent );
		}
	};

	return { start, cancel };
};
