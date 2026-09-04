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
	 * @param list<ManagedVectorMatch> $matches Ordered provider-managed matches.
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
