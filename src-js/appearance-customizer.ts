import {
	applyWidgetAppearance,
	normalizeWidgetAppearance,
	type WidgetAppearance,
} from './widget-appearance';

type ElementFactory = (
	tagName: string,
	props: Record< string, unknown > | null,
	...children: Array< unknown >
) => unknown;

export interface AppearanceCustomizerProps {
	appearance: WidgetAppearance;
	saving: boolean;
	error?: string;
	onChange: ( next: WidgetAppearance ) => void;
	onSave: ( next: WidgetAppearance ) => void;
}

const fieldId = ( name: string ): string =>
	`wp-rag-ai-chatbot-appearance-${ name }`;

const selectOptions = (
	createElement: ElementFactory,
	values: Array< [ string, string ] >
): Array< unknown > =>
	values.map( ( [ value, label ] ) =>
		createElement( 'option', { value }, label )
	);

const readInputValue = ( event: Event ): string =>
	( event.currentTarget as HTMLInputElement ).value;

const readSelectValue = ( event: Event ): string =>
	( event.currentTarget as HTMLSelectElement ).value;

export const AppearanceCustomizer = (
	props: AppearanceCustomizerProps
): unknown => {
	const createElement = window.wp.element.createElement as ElementFactory;
	let draft = normalizeWidgetAppearance( props.appearance );
	let previewNode: HTMLElement | null = null;

	const renderPreview = (): void => {
		if ( previewNode !== null ) {
			applyWidgetAppearance( previewNode, draft );
		}
	};

	const updateDraft = ( patch: Partial< WidgetAppearance > ): void => {
		draft = normalizeWidgetAppearance( { ...draft, ...patch } );
		props.onChange( draft );
		renderPreview();
	};

	const previewRef = ( node: HTMLElement | null ): void => {
		previewNode = node;
		renderPreview();
	};

	const makeSelectField = (
		name: keyof WidgetAppearance,
		label: string,
		options: Array< [ string, string ] >
	): unknown => {
		const id = fieldId( name );

		return createElement(
			'div',
			null,
			createElement( 'label', { htmlFor: id }, label ),
			createElement(
				'select',
				{
					id,
					name,
					value: draft[ name ],
					onChange: ( event: Event ) => {
						updateDraft( { [ name ]: readSelectValue( event ) } );
					},
				},
				...selectOptions( createElement, options )
			)
		);
	};

	return createElement(
		'div',
		null,
		createElement(
			'form',
			{
				'data-appearance-customizer': true,
				onSubmit: ( event: Event ) => {
					event.preventDefault();
					props.onSave( draft );
				},
			},
			createElement(
				'div',
				null,
				createElement(
					'label',
					{ htmlFor: fieldId( 'primary_color' ) },
					'Primary color'
				),
				createElement( 'input', {
					id: fieldId( 'primary_color' ),
					name: 'primary_color',
					type: 'color',
					value: draft.primary_color,
					onChange: ( event: Event ) => {
						updateDraft( { primary_color: readInputValue( event ) } );
					},
				} )
			),
			makeSelectField( 'color_mode', 'Color mode', [
				[ 'system', 'System' ],
				[ 'light', 'Light' ],
				[ 'dark', 'Dark' ],
			] ),
			makeSelectField( 'position', 'Position', [
				[ 'bottom-right', 'Bottom right' ],
				[ 'bottom-left', 'Bottom left' ],
			] ),
			makeSelectField( 'launcher_style', 'Launcher style', [
				[ 'bubble', 'Bubble' ],
				[ 'icon', 'Icon' ],
				[ 'text', 'Text' ],
			] ),
			makeSelectField( 'panel_size', 'Panel size', [
				[ 'small', 'Small' ],
				[ 'medium', 'Medium' ],
				[ 'large', 'Large' ],
			] ),
			createElement(
				'div',
				null,
				createElement(
					'label',
					{ htmlFor: fieldId( 'radius_px' ) },
					'Corner radius'
				),
				createElement( 'input', {
					id: fieldId( 'radius_px' ),
					name: 'radius_px',
					type: 'number',
					min: 0,
					max: 32,
					step: 1,
					value: draft.radius_px,
					onChange: ( event: Event ) => {
						updateDraft( {
							radius_px: Number( readInputValue( event ) ),
						} );
					},
				} )
			),
			makeSelectField( 'font_family', 'Font family', [
				[ 'system', 'System' ],
				[ 'sans', 'Sans serif' ],
				[ 'serif', 'Serif' ],
				[ 'mono', 'Monospace' ],
			] ),
			props.error
				? createElement( 'div', { role: 'alert' }, props.error )
				: undefined,
			createElement(
				'button',
				{
					type: 'submit',
					disabled: props.saving ? true : undefined,
				},
				props.saving ? 'Saving…' : 'Save appearance'
			)
		),
		createElement(
			'div',
			{
				'data-appearance-preview': true,
				role: 'region',
				'aria-label': 'Chat widget preview',
				ref: previewRef,
			},
			createElement(
				'div',
				{ 'aria-hidden': true },
				createElement( 'strong', null, 'Assistant' ),
				createElement( 'p', null, 'How can I help?' ),
				createElement( 'span', null, 'Chat' )
			)
		)
	);
};
