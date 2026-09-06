<?php
/**
 * M10 trusted-filter to M08 portable-filter mapping.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Filter;

use InvalidArgumentException;
use WpRagAiChatbot\VectorStore\Filter\AndFilter;
use WpRagAiChatbot\VectorStore\Filter\EqualsFilter;
use WpRagAiChatbot\VectorStore\Filter\InFilter;
use WpRagAiChatbot\VectorStore\Filter\VectorFilter;

/**
 * Converts only supported trusted constraints into portable M08 filters.
 */
final class VectorFilterMapper {
	/**
	 * Map trusted retrieval constraints without silently broadening access.
	 *
	 * @param RetrievalFilter $filter Trusted server-side retrieval filter.
	 * @throws InvalidArgumentException When a mandatory constraint is unsupported.
	 */
	public function map( RetrievalFilter $filter ): ?VectorFilter {
		if ( array() !== $filter->mandatory ) {
			throw new InvalidArgumentException( 'Unsupported mandatory retrieval filter.' );
		}

		$filters = array();
		if ( null !== $filter->visibility ) {
			$filters[] = new EqualsFilter( 'visibility', $filter->visibility );
		}
		if ( null !== $filter->language ) {
			$filters[] = new EqualsFilter( 'language', $filter->language );
		}
		if ( array() !== $filter->source_ids ) {
			$filters[] = new InFilter( 'source_id', $filter->source_ids );
		}
		if ( array() !== $filter->document_keys ) {
			$filters[] = new InFilter( 'document_key', $filter->document_keys );
		}

		if ( array() === $filters ) {
			return null;
		}
		if ( 1 === count( $filters ) ) {
			return $filters[0];
		}

		return new AndFilter( $filters );
	}
}
