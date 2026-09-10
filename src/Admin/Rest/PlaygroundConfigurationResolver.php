<?php
/**
 * Request-local Playground configuration resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

/**
 * Resolves explicit persisted Playground selectors through existing persistence boundaries.
 */
final class PlaygroundConfigurationResolver {
	/**
	 * Create the resolver.
	 *
	 * @param PlaygroundBotConfigurationResolver       $bots Persisted bot selector.
	 * @param PlaygroundRetrievalConfigurationResolver $retrieval Persisted retrieval selector.
	 */
	public function __construct(
		private readonly PlaygroundBotConfigurationResolver $bots,
		private readonly PlaygroundRetrievalConfigurationResolver $retrieval
	) {
	}

	/**
	 * Resolve one closed persisted Playground configuration without fallbacks.
	 *
	 * @param string $bot_id Explicit persisted bot identifier.
	 * @param int    $source_id Explicit persisted knowledge-source identifier.
	 * @param string $collection_id Explicit persisted vector collection identifier.
	 */
	public function resolve( string $bot_id, int $source_id, string $collection_id ): PlaygroundConfiguration {
		return new PlaygroundConfiguration(
			$this->bots->resolve( $bot_id ),
			$this->retrieval->resolve( $source_id, $collection_id )
		);
	}
}
