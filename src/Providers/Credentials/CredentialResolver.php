<?php
/**
 * Direct-provider credential resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

/**
 * Resolves direct-provider credentials in approved precedence order.
 */
final class CredentialResolver {
	/**
	 * Create a credential resolver.
	 *
	 * @param CredentialSourceReader $reader Runtime source reader.
	 * @param CredentialStore        $store Plugin-managed credential store.
	 */
	public function __construct(
		private CredentialSourceReader $reader,
		private CredentialStore $store
	) {
	}

	/**
	 * Resolve a direct-provider credential.
	 *
	 * @param string $provider_id Provider identifier.
	 */
	public function resolve( string $provider_id ): ?ResolvedCredential {
		$config = DirectProviderCredentialConfig::for_provider( $provider_id );

		$environment = $this->normalized_runtime_value( $this->reader->environment( $config->environment_name ) );
		if ( null !== $environment ) {
			return new ResolvedCredential( new Secret( $environment ), CredentialSource::ENVIRONMENT );
		}

		$constant = $this->normalized_runtime_value( $this->reader->constant( $config->constant_name ) );
		if ( null !== $constant ) {
			return new ResolvedCredential( new Secret( $constant ), CredentialSource::CONSTANT );
		}

		$stored = $this->store->load( $provider_id );
		if ( null !== $stored ) {
			return new ResolvedCredential( $stored, CredentialSource::OPTION );
		}

		return null;
	}

	/**
	 * Trim outer whitespace and reject blank runtime values.
	 *
	 * @param string|null $value Runtime credential source value.
	 */
	private function normalized_runtime_value( ?string $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );
		return '' === $value ? null : $value;
	}
}
