<?php
/**
 * Playground embedding-provider resolver.
 *
 * @package WpRagAiChatbot
 */
declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use OutOfBoundsException;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Resolves the embedding adapter selected by persisted semantic configuration.
 */
final class PlaygroundEmbeddingProviderResolver {
	/**
	 * Registered providers.
	 *
	 * @var ProviderRegistry
	 */
	private ProviderRegistry $providers;

	/**
	 * Create the resolver.
	 *
	 * @param ProviderRegistry $providers Registered providers.
	 */
	public function __construct( ProviderRegistry $providers ) {
		$this->providers = $providers;
	}

	/**
	 * Resolve the exact embedding provider selected by the persisted profile.
	 *
	 * @param PlaygroundSemanticConfiguration $configuration Persisted semantic runtime configuration.
	 * @throws OutOfBoundsException When the persisted provider has no embedding capability.
	 */
	public function resolve( PlaygroundSemanticConfiguration $configuration ): EmbeddingProvider {
		$provider = $this->providers->embedding( $configuration->embedding_profile->provider_id );
		if ( null === $provider ) {
			throw new OutOfBoundsException( 'Embedding provider is not registered.' );
		}

		return $provider;
	}
}
