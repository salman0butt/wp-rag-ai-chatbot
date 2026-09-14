import {
	evaluateDisplayRules,
	normalizeDisplayRules,
	type DisplayDecision,
	type DisplayRuleFacts,
	type DisplayRulesConfig,
} from './display-rules';

type ElementFactory = (
	tagName: string,
	props: Record< string, unknown > | null,
	...children: Array< unknown >
) => unknown;

export interface DisplayRulesEditorProps {
	config: DisplayRulesConfig;
	saving: boolean;
	error?: string;
	onChange: ( next: DisplayRulesConfig ) => void;
	onSave: ( next: DisplayRulesConfig ) => void;
}

const fieldId = ( name: string ): string => `wp-rag-ai-chatbot-rules-${ name }`;

const readValue = ( event: Event ): string => {
	const target = event.currentTarget as
		| HTMLInputElement
		| HTMLSelectElement
		| HTMLTextAreaElement;
	return target.value;
};

const splitLines = ( value: string ): readonly string[] =>
	value.split( /\r?\n/u );

const selectOptions = (
	createElement: ElementFactory,
	values: Array< [ string, string ] >
): Array< unknown > =>
	values.map( ( [ value, label ] ) =>
		createElement( 'option', { value }, label )
	);

export const previewDisplayRules = (
	config: DisplayRulesConfig,
	facts: DisplayRuleFacts
): DisplayDecision => evaluateDisplayRules( config, facts );

export const DisplayRulesEditor = (
	props: DisplayRulesEditorProps
): unknown => {
	const createElement = window.wp.element.createElement as ElementFactory;
	let draft = normalizeDisplayRules( props.config );

	const updateDraft = ( next: DisplayRulesConfig ): void => {
		draft = normalizeDisplayRules( next );
		props.onChange( draft );
	};
	const updateVisibility = (
		patch: Partial< DisplayRulesConfig[ 'visibility' ] >
	): void => {
		updateDraft( {
			...draft,
			visibility: {
				...draft.visibility,
				...patch,
			},
		} );
	};
	const updateLocalization = (
		patch: Partial< DisplayRulesConfig[ 'localization' ] >
	): void => {
		updateDraft( {
			...draft,
			localization: {
				...draft.localization,
				...patch,
			},
		} );
	};
	const includeId = fieldId( 'url-include' );
	const excludeId = fieldId( 'url-exclude' );
	const audienceId = fieldId( 'audience' );
	const localeId = fieldId( 'locale' );
	const directionId = fieldId( 'direction' );

	return createElement(
		'form',
		{
			'data-display-rules-editor': true,
			onSubmit: ( event: Event ) => {
				event.preventDefault();
				props.onSave( draft );
			},
		},
		createElement( 'h2', null, 'Display rules' ),
		createElement(
			'label',
			{ htmlFor: includeId },
			'Show on URL patterns'
		),
		createElement( 'textarea', {
			id: includeId,
			name: 'url_include',
			rows: 4,
			maxLength: 8224,
			value: draft.visibility.url_include.join( '\n' ),
			onChange: ( event: Event ) => {
				updateVisibility( {
					url_include: splitLines( readValue( event ) ),
				} );
			},
		} ),
		createElement(
			'label',
			{ htmlFor: excludeId },
			'Hide on URL patterns'
		),
		createElement( 'textarea', {
			id: excludeId,
			name: 'url_exclude',
			rows: 4,
			maxLength: 8224,
			value: draft.visibility.url_exclude.join( '\n' ),
			onChange: ( event: Event ) => {
				updateVisibility( {
					url_exclude: splitLines( readValue( event ) ),
				} );
			},
		} ),
		createElement( 'label', { htmlFor: audienceId }, 'Audience' ),
		createElement(
			'select',
			{
				id: audienceId,
				name: 'audience',
				value: draft.visibility.audience,
				onChange: ( event: Event ) => {
					updateVisibility( {
						audience: readValue(
							event
						) as DisplayRulesConfig[ 'visibility' ][ 'audience' ],
					} );
				},
			},
			...selectOptions( createElement, [
				[ 'all', 'Everyone' ],
				[ 'authenticated', 'Signed-in visitors' ],
				[ 'anonymous', 'Signed-out visitors' ],
				[ 'selected_roles', 'Selected roles' ],
			] )
		),
		createElement( 'label', { htmlFor: localeId }, 'Locale' ),
		createElement( 'input', {
			id: localeId,
			name: 'locale',
			type: 'text',
			maxLength: 35,
			value: draft.localization.locale,
			onChange: ( event: Event ) => {
				updateLocalization( { locale: readValue( event ) } );
			},
		} ),
		createElement( 'label', { htmlFor: directionId }, 'Text direction' ),
		createElement(
			'select',
			{
				id: directionId,
				name: 'direction',
				value: draft.localization.direction,
				onChange: ( event: Event ) => {
					updateLocalization( {
						direction: readValue(
							event
						) as DisplayRulesConfig[ 'localization' ][ 'direction' ],
					} );
				},
			},
			...selectOptions( createElement, [
				[ 'auto', 'Automatic' ],
				[ 'ltr', 'Left to right' ],
				[ 'rtl', 'Right to left' ],
			] )
		),
		props.error
			? createElement( 'div', { role: 'alert' }, props.error )
			: undefined,
		createElement(
			'button',
			{
				type: 'submit',
				disabled: props.saving ? true : undefined,
			},
			props.saving ? 'Saving…' : 'Save display rules'
		)
	);
};
