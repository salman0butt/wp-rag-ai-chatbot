export {};

declare const require: ( path: string ) => unknown;

type WidgetAppearance = {
	primary_color: string;
	color_mode: 'light' | 'dark' | 'system';
	position: 'bottom-left' | 'bottom-right';
	launcher_style: 'bubble' | 'icon' | 'text';
	panel_size: 'small' | 'medium' | 'large';
	radius_px: number;
	font_family: 'system' | 'sans' | 'serif' | 'mono';
};

type AppearanceCustomizerModule = {
	AppearanceCustomizer: ( props: {
		appearance: WidgetAppearance;
		saving: boolean;
		error?: string;
		onChange: ( next: WidgetAppearance ) => void;
		onSave: ( next: WidgetAppearance ) => void;
	} ) => Node;
};

type TestElementProps = Record< string, unknown > | null;

const createTestElement = (
	tagName: string,
	props: TestElementProps,
	...children: Array< Node | string | number | undefined >
): HTMLElement => {
	const element = document.createElement( tagName );

	for ( const [ key, value ] of Object.entries( props ?? {} ) ) {
		if ( key.startsWith( 'on' ) && typeof value === 'function' ) {
			const eventName = key.slice( 2 ).toLowerCase();
			element.addEventListener( eventName, value as EventListener );
			continue;
		}

		if ( value === undefined ) {
			continue;
		}

		if ( key === 'htmlFor' ) {
			element.setAttribute( 'for', String( value ) );
			continue;
		}

		element.setAttribute( key, String( value ) );
	}

	for ( const child of children ) {
		if ( child !== undefined ) {
			element.append( child instanceof Node ? child : String( child ) );
		}
	}

	return element;
};

const installElementFactory = (): void => {
	Object.defineProperty( window, 'wp', {
		configurable: true,
		value: {
			element: {
				createElement: createTestElement,
			},
		},
	} );
};

const loadedAppearance: WidgetAppearance = {
	primary_color: '#1d4ed8',
	color_mode: 'dark',
	position: 'bottom-left',
	launcher_style: 'text',
	panel_size: 'large',
	radius_px: 24,
	font_family: 'mono',
};

const loadCustomizer = (): AppearanceCustomizerModule =>
	require( './appearance-customizer' ) as AppearanceCustomizerModule;

describe( 'AppearanceCustomizer', () => {
	it( 'renders the bounded seven-field form and previews unsaved changes before explicit save', () => {
		installElementFactory();
		const { AppearanceCustomizer } = loadCustomizer();
		const onChange = jest.fn();
		const onSave = jest.fn();
		const root = document.createElement( 'div' );
		root.append(
			AppearanceCustomizer( {
				appearance: loadedAppearance,
				saving: false,
				onChange,
				onSave,
			} )
		);

		const fieldNames = [
			'primary_color',
			'color_mode',
			'position',
			'launcher_style',
			'panel_size',
			'radius_px',
			'font_family',
		];
		for ( const fieldName of fieldNames ) {
			expect(
				root.querySelector( `[name="${ fieldName }"]` )
			).not.toBeNull();
		}

		const preview = root.querySelector< HTMLElement >(
			'[data-appearance-preview]'
		);
		const color = root.querySelector< HTMLInputElement >(
			'input[name="primary_color"]'
		);
		const radius = root.querySelector< HTMLInputElement >(
			'input[name="radius_px"]'
		);
		const position = root.querySelector< HTMLSelectElement >(
			'select[name="position"]'
		);
		const form = root.querySelector< HTMLFormElement >(
			'form[data-appearance-customizer]'
		);

		expect( preview?.getAttribute( 'role' ) ).toBe( 'region' );
		expect( preview?.getAttribute( 'aria-label' ) ).toBe(
			'Chat widget preview'
		);
		expect( preview?.dataset.wpRagAiChatbotPosition ).toBe( 'bottom-left' );
		expect( color?.value ).toBe( '#1d4ed8' );
		expect( radius?.value ).toBe( '24' );
		expect( radius?.min ).toBe( '0' );
		expect( radius?.max ).toBe( '32' );

		if (
			color === null ||
			radius === null ||
			position === null ||
			form === null
		) {
			return;
		}

		color.value = '#dc2626';
		color.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( onChange ).toHaveBeenLastCalledWith( {
			...loadedAppearance,
			primary_color: '#dc2626',
		} );
		expect(
			preview?.style.getPropertyValue(
				'--wp-rag-ai-chatbot-primary-color'
			)
		).toBe( '#dc2626' );

		radius.value = '12';
		radius.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( onChange ).toHaveBeenLastCalledWith( {
			...loadedAppearance,
			radius_px: 12,
		} );
		expect(
			preview?.style.getPropertyValue( '--wp-rag-ai-chatbot-radius' )
		).toBe( '12px' );

		position.value = 'bottom-right';
		position.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( preview?.dataset.wpRagAiChatbotPosition ).toBe(
			'bottom-right'
		);

		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);
		expect( onSave ).toHaveBeenCalledTimes( 1 );
		expect( onSave ).toHaveBeenCalledWith( {
			primary_color: '#dc2626',
			color_mode: 'dark',
			position: 'bottom-right',
			launcher_style: 'text',
			panel_size: 'large',
			radius_px: 12,
			font_family: 'mono',
		} );
	} );
} );
