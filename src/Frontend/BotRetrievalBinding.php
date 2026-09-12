<?php
/**
 * Bot-scoped public retrieval binding.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;

/**
 * Carries the bounded server-owned knowledge source and vector collection for one bot.
 */
final readonly class BotRetrievalBinding {
	/**
	 * Create one persisted retrieval binding.
	 *
	 * @param int    $source_id Persisted knowledge-source identifier.
	 * @param string $collection_id Persisted vector collection key.
	 * @throws InvalidArgumentException When either identifier is invalid.
	 */
	public function __construct(
		public int $source_id,
		public string $collection_id
	) {
		if ( $source_id < 1 ) {
			throw new InvalidArgumentException( 'Bot retrieval source ID is invalid.' );
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $collection_id ) ) {
			throw new InvalidArgumentException( 'Bot retrieval collection ID is invalid.' );
		}
	}
}
