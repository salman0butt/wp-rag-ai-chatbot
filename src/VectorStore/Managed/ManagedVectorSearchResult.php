<?php
/**
 * Managed vector search result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Managed;

use InvalidArgumentException;

/**
 * Immutable bounded managed search result list.
 */
final class ManagedVectorSearchResult {
	/**
	 * Create a bounded ordered managed search result.
	 *
	 * @param array $matches Ordered provider-managed matches.
	 * @phpstan-param list<ManagedVectorMatch> $matches
	 * @throws InvalidArgumentException When matches are not an ordered list of managed matches.
	 */
	public function __construct( public readonly array $matches ) {
		if ( ! array_is_list( $matches ) ) {
			throw new InvalidArgumentException( 'Managed vector matches must be an ordered list.' );
		}
		foreach ( $matches as $match ) {
			if ( ! $match instanceof ManagedVectorMatch ) {
				throw new InvalidArgumentException( 'Managed vector match is invalid.' );
			}
		}
	}
}
