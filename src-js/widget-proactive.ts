type ProactiveDelayConfig = {
	enabled: boolean;
	delayMs: number | null;
	scrollPercent: number | null;
};

export type ProactiveDelayCoordinator = {
	start: () => void;
	cancel: () => void;
};

const MAX_TIMER_MS = 600000;
const MAX_SCROLL_PERCENT = 100;

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
	};
};

export const createProactiveDelayCoordinator = (
	config: ProactiveDelayConfig,
	onOpen: () => void
): ProactiveDelayCoordinator => {
	let timer: ReturnType< typeof setTimeout > | null = null;
	let scrollFrame: number | null = null;
	let listeningForScroll = false;
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

	const cancel = (): void => {
		completed = true;
		if ( timer !== null ) {
			clearTimeout( timer );
			timer = null;
		}
		stopScrollListener();
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
	};

	return { start, cancel };
};
