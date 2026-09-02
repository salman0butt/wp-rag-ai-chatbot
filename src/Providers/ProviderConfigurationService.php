<?php
/**
 * Non-secret provider configuration service.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use WpRagAiChatbot\Providers\Credentials\CredentialResolver;
use WpRagAiChatbot\Providers\Credentials\CredentialSource;

/**
 * Describes provider state without issuing paid or discovery requests.
 */
final class ProviderConfigurationService {
	/**
	 * Create a provider configuration service.
	 *
	 * @param ProviderRegistry   $registry Registered providers.
	 * @param CredentialResolver $credentials Direct-provider credential resolver.
	 */
	public function __construct(
		private readonly ProviderRegistry $registry,
		private readonly CredentialResolver $credentials
	) {}

	/**
	 * Return one safe provider descriptor.
	 *
	 * @param string $provider_id Stable provider identifier.
	 */
	public function describe( string $provider_id ): ProviderDescriptor {
		$provider = $this->registry->generation( $provider_id );
		$catalog  = $this->registry->catalog( $provider_id );

		if ( ProviderIds::WORDPRESS_AI_CLIENT === $provider_id ) {
			$available = $provider->available();
			return new ProviderDescriptor(
				$provider_id,
				'WordPress AI Client',
				new ProviderHealth(
					$provider_id,
					$available ? ProviderHealthStatus::CONFIGURED : ProviderHealthStatus::UNAVAILABLE
				),
				$available ? CredentialSource::CORE : CredentialSource::NONE,
				array( 'generation' )
			);
		}

		$resolved = $this->credentials->resolve( $provider_id );
		$source   = null === $resolved ? CredentialSource::NONE : $resolved->source;
		$status   = null === $resolved ? ProviderHealthStatus::UNCONFIGURED : ProviderHealthStatus::CONFIGURED;

		return new ProviderDescriptor(
			$provider_id,
			$this->display_name( $provider_id ),
			new ProviderHealth( $provider_id, $status ),
			$source,
			null === $catalog ? array( 'generation' ) : array( 'generation', 'model_catalog' )
		);
	}

	/**
	 * Return descriptors for all registered providers.
	 *
	 * @return ProviderDescriptor[]
	 */
	public function all(): array {
		$descriptors = array();
		foreach ( $this->registry->ids() as $provider_id ) {
			$descriptors[] = $this->describe( $provider_id );
		}
		return $descriptors;
	}

	/**
	 * Return the fixed human-readable label for direct providers.
	 *
	 * @param string $provider_id Stable provider identifier.
	 */
	private function display_name( string $provider_id ): string {
		return match ( $provider_id ) {
			ProviderIds::OPENAI_DIRECT => 'OpenAI Direct',
			ProviderIds::OPENROUTER_DIRECT => 'OpenRouter Direct',
			default => $provider_id,
		};
	}
}
