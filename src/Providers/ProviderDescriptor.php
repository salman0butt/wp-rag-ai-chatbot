<?php
/**
 * Safe provider descriptor.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use WpRagAiChatbot\Providers\Credentials\CredentialSource;

/**
 * Exposes only non-secret provider configuration metadata.
 */
final readonly class ProviderDescriptor {
	/**
	 * Create a provider descriptor.
	 *
	 * @param string           $provider_id Stable provider identifier.
	 * @param string           $display_name Human-readable provider label.
	 * @param ProviderHealth   $health Local provider health/configuration state.
	 * @param CredentialSource $credential_source Non-secret credential source type.
	 * @param string[]         $capabilities Supported normalized capabilities.
	 */
	public function __construct(
		public string $provider_id,
		public string $display_name,
		public ProviderHealth $health,
		public CredentialSource $credential_source,
		public array $capabilities
	) {}
}
