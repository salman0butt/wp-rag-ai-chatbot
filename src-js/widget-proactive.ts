type ProactiveDelayConfig = {
	enabled: boolean;
	delayMs: number | null;
	scrollPercent: number | null;
	inactivityMs: number | null;
	exitIntent: boolean;
	firstVisitOnly: boolean;
	clickSelector: string | null;
};

export type ProactiveDelayCoordinator = {
	start: () => void;
	cancel: () => void;
};

type ProactiveCoordinatorContext = {
	botId: string;
	documentRoot: Document;
};

const MAX_TIMER_MS = 600000;
const MAX_SCROLL_PERCENT = 100;
const MAX_CLICK_SELECTOR_BYTES = 160;
const INACTIVITY_EVENTS = [ 'pointerdown', 'keydown' ] as const;
const FINE_POINTER_QUERY = '(hover: hover) and (pointer: fine)';
const SAFE_CLICK_SELECTOR =
	/^(?:[a-zA-Z][a-zA-Z0-9_-]*)?(?:[.#][a-zA-Z_][a-zA-Z0-9_-]*|\[[a-zA-Z_][a-zA-Z0-9_-]*(?:=(?:"[^"]*"|'[^']*'|[a-zA-Z0-9_-]+))?\])*$/;
const FIRST_VISIT_KEY_PREFIX = 'wp-rag-ai-chatbot:proactive-seen:';
const sessionSeenBots = new Set< string >();

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

const utf8ByteLength = ( value: string ): number => {
	let bytes = 0;
	for ( const character of value ) {
		const codePoint = character.codePointAt( 0 ) ?? 0;
		if ( codePoint <= 0x7f ) {
			bytes += 1;
		} else if ( codePoint <= 0x7ff ) {
			bytes += 2;
		} else if ( codePoint <= 0xffff ) {
			bytes += 3;
		} else {
			bytes += 4;
		}
	}
	return bytes;
};

const normalizeClickSelector = ( value: unknown ): string | null => {
	if ( typeof value !== 'string' ) {
		return null;
	}

	const selector = value.trim();
	if ( selector === '' || ! SAFE_CLICK_SELECTOR.test( selector ) ) {
		return null;
	}

	if ( utf8ByteLength( selector ) > MAX_CLICK_SELECTOR_BYTES ) {
		return null;
	}

	return selector;
};

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
		firstVisitOnly: proactive.first_visit_only === true,
		clickSelector: normalizeClickSelector( proactive.click_selector ),
	};
};

export const createProactiveDelayCoordinator = (
	config: ProactiveDelayConfig,
	onOpen: () => void,
	context?: ProactiveCoordinatorContext
): ProactiveDelayCoordinator => {
	let timer: ReturnType< typeof setTimeout > | null = null;
	let inactivityTimer: ReturnType< typeof setTimeout > | null = null;
	let scrollFrame: number | null = null;
	let listeningForScroll = false;
	let listeningForActivity = false;
	let listeningForExitIntent = false;
	let listeningForClick = false;
	let completed = false;

	const documentRoot = context?.documentRoot ?? document;

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

	const stopClickListener = (): void => {
		if ( listeningForClick ) {
			documentRoot.removeEventListener( 'click', handleClick );
			listeningForClick = false;
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
		stopClickListener();
		onOpen();
	};

	const evaluateScroll = (): void => {
		if ( completed || config.scrollPercent === null ) {
			return;
		}

		const root = documentRoot.documentElement;
		const scrollableDistance = Math.max(
			0,
			root.scrollHeight - root.clientHeight
		);
		if ( scrollableDistance === 0 ) {
			return;
		}

		const view = documentRoot.defaultView ?? window;
		const percent =
			( view.scrollY / scrollableDistance ) * MAX_SCROLL_PERCENT;

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

	function handleClick( event: Event ): void {
		if ( completed || config.clickSelector === null ) {
			return;
		}

		const target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}

		if ( target.closest( config.clickSelector ) !== null ) {
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
		stopInactivityListener();
		stopExitIntentListener();
		stopClickListener();
	};

	const applyFirstVisitGate = (): boolean => {
		if ( ! config.firstVisitOnly ) {
			return true;
		}

		if ( context === undefined || context.botId === '' ) {
			return false;
		}

		if ( sessionSeenBots.has( context.botId ) ) {
			return false;
		}

		const key = `${ FIRST_VISIT_KEY_PREFIX }${ context.botId }`;
		try {
			if (
				context.documentRoot.defaultView?.localStorage.getItem(
					key
				) === '1'
			) {
				sessionSeenBots.add( context.botId );
				return false;
			}
		} catch {
			// Storage is optional; the session-local marker below is the fallback.
		}

		sessionSeenBots.add( context.botId );
		try {
			context.documentRoot.defaultView?.localStorage.setItem( key, '1' );
		} catch {
			// Storage is optional; session-local scoping already preserves semantics.
		}

		return true;
	};

	const start = (): void => {
		if ( completed || ! config.enabled ) {
			return;
		}

		if ( ! applyFirstVisitGate() ) {
			completed = true;
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

		if ( ! listeningForClick && config.clickSelector !== null ) {
			listeningForClick = true;
			documentRoot.addEventListener( 'click', handleClick );
		}
	};

	return { start, cancel };
};
