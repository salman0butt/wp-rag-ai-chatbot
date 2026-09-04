<?php
/**
 * Portable metadata conjunction filter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Filter;

use InvalidArgumentException;

/**
 * Bounded conjunction of portable filters.
 */
final class AndFilter implements VectorFilter {
	/** Maximum children in one conjunction. */
	private const MAX_FILTERS = 16;

	/**
	 * Ordered child filters.
	 *
	 * @var list<VectorFilter>
	 */
	public readonly array $filters;

	/**
	 * Create a conjunction filter.
	 *
	 * @param array $filters Untrusted child filter values.
	 * @phpstan-param array<array-key, mixed> $filters
	 * @throws InvalidArgumentException When the list is invalid.
	 */
	public function __construct( array $filters ) {
		if ( ! array_is_list( $filters ) || array() === $filters || count( $filters ) > self::MAX_FILTERS ) {
			throw new InvalidArgumentException( 'Vector conjunction filter list is invalid.' );
		}
		foreach ( $filters as $filter ) {
			if ( ! $filter instanceof VectorFilter ) {
				throw new InvalidArgumentException( 'Vector conjunction entries must be portable filters.' );
			}
		}

		/**
		 * Validated child filters.
		 *
		 * @var list<VectorFilter> $filters
		 */
		$this->filters = $filters;
	}

	/**
	 * Determine whether metadata matches.
	 *
	 * @param array<string, scalar> $metadata Portable metadata.
	 */
	public function matches( array $metadata ): bool {
		foreach ( $this->filters as $filter ) {
			if ( ! $filter->matches( $metadata ) ) {
				return false;
			}
		}

		return true;
	}
}
