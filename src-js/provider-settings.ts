export interface ProviderCredentialState {
	configured: boolean;
	source: string;
}

export interface ProviderModelChoice {
	model_id: string;
	display_name: string;
}

export type ProviderSettingsIssue =
	| 'missing_credential'
	| 'provider_unavailable'
	| 'unsupported_capability';

export interface ProviderSettingsScreenProps {
	providerId: string;
	credential: ProviderCredentialState;
	models?: ReadonlyArray< ProviderModelChoice >;
	issue?: ProviderSettingsIssue;
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

const ISSUE_MESSAGES: Readonly< Record< ProviderSettingsIssue, string > > = {
	missing_credential:
		'Add a provider credential to load compatible generation models.',
	provider_unavailable:
		'This provider is currently unavailable. Try again or choose another provider.',
	unsupported_capability:
		'This provider does not offer compatible generation models. Choose another provider.',
};

export const ProviderSettingsScreen = ( {
	providerId,
	credential,
	models = [],
	issue,
	onReplace,
}: ProviderSettingsScreenProps ): unknown => {
	const createElement = window.wp.element
		.createElement as unknown as ElementFactory;
	const sourceLabel =
		SOURCE_LABELS[ credential.source ] ?? 'Configured externally';
	const credentialId = `provider-${ providerId }-credential`;
	const modelId = `provider-${ providerId }-model`;
	const issueContent =
		issue === undefined
			? undefined
			: createElement(
					'div',
					{ 'data-provider-issue': issue, role: 'alert' },
					ISSUE_MESSAGES[ issue ]
			  );
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
		issueContent,
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
