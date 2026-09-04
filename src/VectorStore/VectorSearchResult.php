<?php
/**
 * Vector search result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use InvalidArgumentException;

/**
 * Ordered immutable vector matches.
 */
final class VectorSearchResult {
	/**
	 * Ordered matches.
	 *
	 * @var list<VectorMatch>
	 */
	public readonly array $matches;

	/**
	 * Create a search result.
	 *
	 * @param array $matches Ordered matches.
	 * @throws InvalidArgumentException When matches are not an ordered list.
	 */
	public function __construct( array $matches ) {
		if ( ! array_is_list( $matches ) ) {
			throw new InvalidArgumentException( 'Vector search matches must be an ordered list.' );
		}
		foreach ( $matches as $vector_match ) {
			if ( ! $vector_match instanceof VectorMatch ) {
				throw new InvalidArgumentException( 'Vector search result contains an invalid match.' );
			}
		}

		/** @var list<VectorMatch> $matches */
		$this->matches = $matches;
	}
}
