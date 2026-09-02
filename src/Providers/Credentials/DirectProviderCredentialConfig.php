<?php
/**
 * Direct-provider credential configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Credentials;

use InvalidArgumentException;
use WpRagAiChatbot\Providers\ProviderIds;

/**
 * Fixed server-side names for supported direct-provider credentials.
 */
final readonly class DirectProviderCredentialConfig {
	/**
	 * Create a direct-provider credential configuration.
	 *
	 * @param string $provider_id Provider identifier.
	 * @param string $environment_name Environment variable name.
	 * @param string $constant_name PHP constant name.
	 * @param string $option_name WordPress option name.
	 */
	private function __construct(
		public string $provider_id,
		public string $environment_name,
		public string $constant_name,
		public string $option_name
	) {
	}

	/**
	 * Return the fixed credential configuration for a direct provider.
	 *
	 * @param string $provider_id Provider identifier.
	 * @throws InvalidArgumentException When the provider is not a supported direct provider.
	 */
	public static function for_provider( string $provider_id ): self {
		return match ( $provider_id ) {
			ProviderIds::OPENAI_DIRECT => new self(
				ProviderIds::OPENAI_DIRECT,
				'OPENAI_API_KEY',
				'OPENAI_API_KEY',
				'wp_rag_ai_openai_api_key'
			),
			ProviderIds::OPENROUTER_DIRECT => new self(
				ProviderIds::OPENROUTER_DIRECT,
				'OPENROUTER_API_KEY',
				'OPENROUTER_API_KEY',
				'wp_rag_ai_openrouter_api_key'
			),
			default => throw new InvalidArgumentException( 'Unsupported direct provider.' ),
		};
	}
}
