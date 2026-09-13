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
	value >= 0 &&
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
	let listeningForScroll = false;
	let completed = false;

	const stopScrollListener = (): void => {
		if ( listeningForScroll ) {
			window.removeEventListener( 'scroll', handleScroll );
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

	function handleScroll(): void {
		if ( completed || config.scrollPercent === null ) {
			return;
		}

		const root = document.documentElement;
		const scrollableDistance = Math.max(
			0,
			root.scrollHeight - root.clientHeight
		);
		const percent =
			scrollableDistance === 0
				? MAX_SCROLL_PERCENT
				: ( window.scrollY / scrollableDistance ) * MAX_SCROLL_PERCENT;

		if ( percent >= config.scrollPercent ) {
			complete();
		}
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
			window.addEventListener( 'scroll', handleScroll, { passive: true } );
			handleScroll();
		}
	};

	return { start, cancel };
};
