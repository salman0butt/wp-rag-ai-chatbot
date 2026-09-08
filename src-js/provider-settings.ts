export interface ProviderCredentialState {
	configured: boolean;
	source: string;
}

export interface ProviderModelChoice {
	model_id: string;
	display_name: string;
}

export interface ProviderSettingsScreenProps {
	providerId: string;
	credential: ProviderCredentialState;
	models?: ReadonlyArray< ProviderModelChoice >;
	onReplace?: ( credential: string ) => Promise< void >;
}

type ElementFactory = (
	type: string,
	props: Record< string, unknown > | null,
	...children: unknown[]
) => unknown;

const SOURCE_LABELS: Readonly< Record< string, string > > = {
	managed: 'Managed by this plugin',
	environment: 'Provided by the server environment',
	constant: 'Provided by WordPress configuration',
	none: 'Not configured',
};

export const ProviderSettingsScreen = ( {
	providerId,
	credential,
	models = [],
	onReplace,
}: ProviderSettingsScreenProps ): unknown => {
	const createElement = window.wp.element
		.createElement as unknown as ElementFactory;
	const sourceLabel =
		SOURCE_LABELS[ credential.source ] ?? 'Configured externally';
	const credentialId = `provider-${ providerId }-credential`;
	const modelId = `provider-${ providerId }-model`;
	const modelOptions = models.map( ( model ) =>
		createElement(
			'option',
			{ key: model.model_id, value: model.model_id },
			model.display_name
		)
	);
	const modelSelector =
		models.length === 0
			? undefined
			: createElement(
					'div',
					{ 'data-provider-model-selection': true },
					createElement( 'h2', null, 'Generation model' ),
					createElement( 'label', { htmlFor: modelId }, 'Model' ),
					createElement(
						'select',
						{ id: modelId, name: 'model_id' },
						...modelOptions
					)
			  );

	return createElement(
		'section',
		{ 'data-provider-settings': providerId },
		createElement( 'h2', null, 'Provider credential' ),
		createElement(
			'p',
			{ role: 'status', 'aria-live': 'polite' },
			credential.configured
				? 'Credential configured'
				: 'Credential not configured'
		),
		createElement( 'p', { 'data-credential-source': true }, sourceLabel ),
		createElement(
			'form',
			{
				'data-provider-credential-form': true,
				onSubmit: async ( event: Event ) => {
					event.preventDefault();

					if ( onReplace === undefined ) {
						return;
					}

					const form = event.currentTarget as HTMLFormElement;
					const input = form.elements.namedItem(
						'credential'
					) as HTMLInputElement | null;

					if ( input === null || input.value.trim() === '' ) {
						return;
					}

					await onReplace( input.value );
					input.value = '';
				},
			},
			createElement(
				'label',
				{ htmlFor: credentialId },
				credential.configured ? 'Replace credential' : 'Credential'
			),
			createElement( 'input', {
				autoComplete: 'new-password',
				id: credentialId,
				name: 'credential',
				type: 'password',
			} ),
			createElement(
				'button',
				{ type: 'submit' },
				credential.configured ? 'Replace credential' : 'Save credential'
			)
		),
		modelSelector
	);
};
