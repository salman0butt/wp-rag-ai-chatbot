export interface ProviderCredentialState {
	configured: boolean;
	source: string;
}

export interface ProviderSettingsScreenProps {
	providerId: string;
	credential: ProviderCredentialState;
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
			'label',
			{ htmlFor: credentialId },
			credential.configured ? 'Replace credential' : 'Credential'
		),
		createElement( 'input', {
			autoComplete: 'new-password',
			id: credentialId,
			name: 'credential',
			type: 'password',
		} )
	);
};
