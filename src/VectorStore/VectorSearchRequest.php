<?php
/**
 * Vector search request.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use InvalidArgumentException;
use WpRagAiChatbot\VectorStore\Filter\VectorFilter;

/**
 * Immutable compatibility-checked vector search request.
 */
final class VectorSearchRequest {
	/** Maximum portable top-K request. */
	private const MAX_TOP_K = 100;

	/**
	 * Create a search request.
	 *
	 * @param VectorCollection  $collection Collection boundary.
	 * @param list<int|float>   $vector Query vector.
	 * @param int               $top_k Result count limit.
	 * @param string            $compatibility_fingerprint Compatibility fingerprint.
	 * @param VectorFilter|null $filter Optional portable filter.
	 * @throws InvalidArgumentException When the request is invalid or incompatible.
	 */
	public function __construct(
		public readonly VectorCollection $collection,
		public readonly array $vector,
		public readonly int $top_k,
		public readonly string $compatibility_fingerprint,
		public readonly ?VectorFilter $filter = null
	) {
		if ( $top_k < 1 || $top_k > self::MAX_TOP_K ) {
			throw new InvalidArgumentException( 'Vector search top-K is outside the portable bounds.' );
		}
		if ( ! array_is_list( $vector ) || count( $vector ) !== $collection->profile->embedding->dimensions ) {
			throw new InvalidArgumentException( 'Vector search dimensions do not match the collection profile.' );
		}
		foreach ( $vector as $value ) {
			if ( ( ! is_int( $value ) && ! is_float( $value ) ) || ! is_finite( (float) $value ) ) {
				throw new InvalidArgumentException( 'Vector search values must be finite numeric values.' );
			}
		}
		if ( ! hash_equals( $collection->profile->fingerprint(), $compatibility_fingerprint ) ) {
			throw new InvalidArgumentException( 'Vector search compatibility fingerprint does not match its collection.' );
		}
	}
}
