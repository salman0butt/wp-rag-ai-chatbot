<?php
/**
 * Playground generation-provider resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use OutOfBoundsException;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\ProviderRegistry;

/**
 * Resolves the generation adapter selected by persisted bot configuration.
 */
final class PlaygroundGenerationProviderResolver {
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
	 * Resolve the exact generation provider selected by the persisted bot.
	 *
	 * @param PlaygroundConfiguration $configuration Closed persisted Playground configuration.
	 * @throws OutOfBoundsException When the persisted provider is not registered.
	 */
	public function resolve( PlaygroundConfiguration $configuration ): GenerationProvider {
		return $this->providers->generation( $configuration->bot->provider_id );
	}
}
