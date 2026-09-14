import {
	normalizeDisplayRules,
	type DisplayRulesConfig,
} from './display-rules';

export {};

declare const require: ( path: string ) => unknown;

type DisplayRulesEditorModule = {
	DisplayRulesEditor: ( props: {
		config: DisplayRulesConfig;
		saving: boolean;
		error?: string;
		onChange: ( next: DisplayRulesConfig ) => void;
		onSave: ( next: DisplayRulesConfig ) => void;
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

const loadEditor = (): DisplayRulesEditorModule =>
	require( './display-rules-editor' ) as DisplayRulesEditorModule;

const loadedConfig = normalizeDisplayRules( {
	visibility: {
		url_include: [ '/pricing' ],
		audience: 'authenticated',
	},
	starters: {
		default: [ 'Ask about pricing' ],
	},
	localization: {
		locale: 'en-us',
		direction: 'auto',
	},
} );

describe( 'DisplayRulesEditor', () => {
	it( 'renders labelled native controls and saves only normalized configuration', () => {
		installElementFactory();
		const { DisplayRulesEditor } = loadEditor();
		const onChange = jest.fn();
		const onSave = jest.fn();
		const root = document.createElement( 'div' );
		root.append(
			DisplayRulesEditor( {
				config: loadedConfig,
				saving: false,
				onChange,
				onSave,
			} )
		);

		const form = root.querySelector< HTMLFormElement >(
			'form[data-display-rules-editor]'
		);
		const include = root.querySelector< HTMLTextAreaElement >(
			'textarea[name="url_include"]'
		);
		const audience = root.querySelector< HTMLSelectElement >(
			'select[name="audience"]'
		);
		const locale = root.querySelector< HTMLInputElement >(
			'input[name="locale"]'
		);
		const direction = root.querySelector< HTMLSelectElement >(
			'select[name="direction"]'
		);

		expect( form ).not.toBeNull();
		expect( include ).not.toBeNull();
		expect( audience ).not.toBeNull();
		expect( locale?.maxLength ).toBe( 35 );
		expect( direction ).not.toBeNull();
		expect(
			root.querySelector(
				'label[for="wp-rag-ai-chatbot-rules-url-include"]'
			)
		)?.not.toBeNull();
		expect(
			root.querySelector(
				'label[for="wp-rag-ai-chatbot-rules-audience"]'
			)
		)?.not.toBeNull();

		if (
			form === null ||
			include === null ||
			audience === null ||
			locale === null ||
			direction === null
		) {
			return;
		}

		include.value = ' /pricing \n/docs\n/pricing ';
		include.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( onChange ).toHaveBeenLastCalledWith( {
			...loadedConfig,
			visibility: {
				...loadedConfig.visibility,
				url_include: [ '/pricing', '/docs' ],
			},
		} );

		audience.value = 'anonymous';
		audience.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		form.dispatchEvent(
			new Event( 'submit', { bubbles: true, cancelable: true } )
		);

		expect( onSave ).toHaveBeenCalledTimes( 1 );
		expect( onSave ).toHaveBeenCalledWith( {
			...loadedConfig,
			visibility: {
				...loadedConfig.visibility,
				url_include: [ '/pricing', '/docs' ],
				audience: 'anonymous',
			},
		} );
	} );
} );
