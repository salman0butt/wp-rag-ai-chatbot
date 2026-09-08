export interface ProviderCredentialState {
	configured: boolean;
	source: string;
}

export interface ProviderSettingsScreenProps {
	providerId: string;
	credential: ProviderCredentialState;
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
	onReplace,
}: ProviderSettingsScreenProps ): unknown => {
	const createElement = window.wp.element
		.createElement as unknown as ElementFactory;
	const sourceLabel =
		SOURCE_LABELS[ credential.source ] ?? 'Configured externally';
	const credentialId = `provider-${ providerId }-credential`;

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
		)
	);
};
