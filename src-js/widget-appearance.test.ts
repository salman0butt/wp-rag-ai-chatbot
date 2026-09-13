export {};

declare const require: ( path: string ) => unknown;

type WidgetAppearance = {
	primary_color: string;
	color_mode: string;
	position: string;
	launcher_style: string;
	panel_size: string;
	radius_px: number;
	font_family: string;
};

type AppearanceModule = {
	normalizeWidgetAppearance: ( value: unknown ) => WidgetAppearance;
	applyWidgetAppearance: (
		element: HTMLElement,
		appearance: WidgetAppearance
	) => void;
};

const loadAppearanceModule = (): AppearanceModule =>
	require( './widget-appearance' ) as AppearanceModule;

describe( 'shared widget appearance renderer', () => {
	it( 'applies the complete normalized appearance using the runtime presentation tokens', () => {
		const { normalizeWidgetAppearance, applyWidgetAppearance } =
			loadAppearanceModule();
		const mount = document.createElement( 'div' );
		const appearance = normalizeWidgetAppearance( {
			primary_color: '#1d4ed8',
			color_mode: 'dark',
			position: 'bottom-left',
			launcher_style: 'text',
			panel_size: 'large',
			radius_px: 24,
			font_family: 'mono',
		} );

		applyWidgetAppearance( mount, appearance );

		expect( mount.dataset.wpRagAiChatbotPosition ).toBe( 'bottom-left' );
		expect( mount.dataset.wpRagAiChatbotColorMode ).toBe( 'dark' );
		expect( mount.dataset.wpRagAiChatbotLauncherStyle ).toBe( 'text' );
		expect( mount.dataset.wpRagAiChatbotPanelSize ).toBe( 'large' );
		expect( mount.dataset.wpRagAiChatbotFontFamily ).toBe( 'mono' );
		expect(
			mount.style.getPropertyValue( '--wp-rag-ai-chatbot-primary-color' )
		).toBe( '#1d4ed8' );
		expect(
			mount.style.getPropertyValue( '--wp-rag-ai-chatbot-radius' )
		).toBe( '24px' );
	} );

	it( 'falls back to the existing safe defaults without projecting arbitrary style input', () => {
		const { normalizeWidgetAppearance, applyWidgetAppearance } =
			loadAppearanceModule();
		const mount = document.createElement( 'div' );
		const appearance = normalizeWidgetAppearance( {
			primary_color: 'red; background:url(javascript:alert(1))',
			color_mode: 'unsafe',
			position: 'top-left',
			launcher_style: 'custom',
			panel_size: 'huge',
			radius_px: 100,
			font_family: 'url(evil)',
			custom_css: 'body { display: none }',
		} );

		applyWidgetAppearance( mount, appearance );

		expect( appearance ).toEqual( {
			primary_color: '#2563eb',
			color_mode: 'system',
			position: 'bottom-right',
			launcher_style: 'bubble',
			panel_size: 'medium',
			radius_px: 16,
			font_family: 'system',
		} );
		expect( mount.getAttribute( 'style' ) ).not.toContain( 'javascript:' );
		expect( mount.getAttribute( 'style' ) ).not.toContain( 'display: none' );
	} );
} );
