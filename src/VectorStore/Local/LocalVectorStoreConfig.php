<?php
/**
 * Local vector-store configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Local;

use InvalidArgumentException;

/**
 * Hard bounds for the local WordPress vector store.
 */
final class LocalVectorStoreConfig {
	/**
	 * Create bounded local-search configuration.
	 *
	 * @param int $candidate_limit Maximum database rows that may reach PHP similarity scoring.
	 * @param int $max_top_k Maximum requested result count.
	 */
	public function __construct(
		public readonly int $candidate_limit,
		public readonly int $max_top_k
	) {
		if ( $candidate_limit < 1 || $max_top_k < 1 ) {
			throw new InvalidArgumentException( 'Local vector-store limits must be positive.' );
		}
		if ( $max_top_k > $candidate_limit ) {
			throw new InvalidArgumentException( 'Local vector-store top-K cannot exceed the candidate limit.' );
		}
	}
}
