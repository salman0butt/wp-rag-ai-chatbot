export type WidgetAppearance = {
	primary_color: string;
	color_mode: 'light' | 'dark' | 'system';
	position: 'bottom-left' | 'bottom-right';
	launcher_style: 'bubble' | 'icon' | 'text';
	panel_size: 'small' | 'medium' | 'large';
	radius_px: number;
	font_family: 'system' | 'sans' | 'serif' | 'mono';
};

const COLOR_MODES = [ 'light', 'dark', 'system' ] as const;
const POSITIONS = [ 'bottom-left', 'bottom-right' ] as const;
const LAUNCHER_STYLES = [ 'bubble', 'icon', 'text' ] as const;
const PANEL_SIZES = [ 'small', 'medium', 'large' ] as const;
const FONT_FAMILIES = [ 'system', 'sans', 'serif', 'mono' ] as const;

const readChoice = < T extends string >(
	appearance: Record< string, unknown >,
	key: string,
	allowed: readonly T[],
	fallback: T
): T => {
	const value = appearance[ key ];

	return typeof value === 'string' && allowed.includes( value as T )
		? ( value as T )
		: fallback;
};

const readPrimaryColor = ( appearance: Record< string, unknown > ): string => {
	const value = appearance.primary_color;

	return typeof value === 'string' && /^#[0-9a-f]{6}$/.test( value )
		? value
		: '#2563eb';
};

const readRadius = ( appearance: Record< string, unknown > ): number => {
	const value = appearance.radius_px;

	return typeof value === 'number' &&
		Number.isInteger( value ) &&
		value >= 0 &&
		value <= 32
		? value
		: 16;
};

export const normalizeWidgetAppearance = (
	value: unknown
): WidgetAppearance => {
	const appearance =
		typeof value === 'object' && value !== null
			? ( value as Record< string, unknown > )
			: {};

	return {
		primary_color: readPrimaryColor( appearance ),
		color_mode: readChoice(
			appearance,
			'color_mode',
			COLOR_MODES,
			'system'
		),
		position: readChoice(
			appearance,
			'position',
			POSITIONS,
			'bottom-right'
		),
		launcher_style: readChoice(
			appearance,
			'launcher_style',
			LAUNCHER_STYLES,
			'bubble'
		),
		panel_size: readChoice(
			appearance,
			'panel_size',
			PANEL_SIZES,
			'medium'
		),
		radius_px: readRadius( appearance ),
		font_family: readChoice(
			appearance,
			'font_family',
			FONT_FAMILIES,
			'system'
		),
	};
};

export const applyWidgetAppearance = (
	mount: HTMLElement,
	appearance: WidgetAppearance
): void => {
	mount.dataset.wpRagAiChatbotPosition = appearance.position;
	mount.dataset.wpRagAiChatbotColorMode = appearance.color_mode;
	mount.dataset.wpRagAiChatbotLauncherStyle = appearance.launcher_style;
	mount.dataset.wpRagAiChatbotPanelSize = appearance.panel_size;
	mount.dataset.wpRagAiChatbotFontFamily = appearance.font_family;
	mount.style.setProperty(
		'--wp-rag-ai-chatbot-primary-color',
		appearance.primary_color
	);
	mount.style.setProperty(
		'--wp-rag-ai-chatbot-radius',
		`${ appearance.radius_px }px`
	);
};
