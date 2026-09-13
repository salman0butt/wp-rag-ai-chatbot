type ProactiveDelayConfig = {
	enabled: boolean;
	delayMs: number | null;
};

export type ProactiveDelayCoordinator = {
	start: () => void;
	cancel: () => void;
};

const MAX_TIMER_MS = 600000;

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

export const readProactiveDelayConfig = (
	displayRules: unknown
): ProactiveDelayConfig => {
	const rules = asRecord( displayRules );
	const proactive = asRecord( rules.proactive );

	return {
		enabled: proactive.enabled === true,
		delayMs: normalizeDelay( proactive.delay_ms ),
	};
};

export const createProactiveDelayCoordinator = (
	config: ProactiveDelayConfig,
	onOpen: () => void
): ProactiveDelayCoordinator => {
	let timer: ReturnType< typeof setTimeout > | null = null;
	let completed = false;

	const cancel = (): void => {
		completed = true;
		if ( timer !== null ) {
			clearTimeout( timer );
			timer = null;
		}
	};

	const start = (): void => {
		if (
			completed ||
			timer !== null ||
			! config.enabled ||
			config.delayMs === null
		) {
			return;
		}

		timer = setTimeout( () => {
			timer = null;
			if ( completed ) {
				return;
			}

			completed = true;
			onOpen();
		}, config.delayMs );
	};

	return { start, cancel };
};
