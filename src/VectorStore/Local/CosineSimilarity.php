<?php
/**
 * Cosine similarity for local vector search.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Local;

use InvalidArgumentException;

/**
 * Computes deterministic cosine similarity over validated vectors.
 */
final class CosineSimilarity {
	/**
	 * Score two equal-dimension finite non-zero vectors.
	 *
	 * @param array<int, mixed> $query Query vector.
	 * @param array<int, mixed> $candidate Candidate vector.
	 * @throws InvalidArgumentException When vectors are invalid or incompatible.
	 */
	public static function score( array $query, array $candidate ): float {
		if ( false === array_is_list( $query ) || false === array_is_list( $candidate ) || count( $query ) !== count( $candidate ) || array() === $query ) {
			throw new InvalidArgumentException( 'Cosine vectors must be non-empty ordered lists with matching dimensions.' );
		}

		$dot            = 0.0;
		$query_norm     = 0.0;
		$candidate_norm = 0.0;
		foreach ( $query as $index => $query_value ) {
			$candidate_value = $candidate[ $index ];
			if ( ( ! is_int( $query_value ) && ! is_float( $query_value ) ) || ( ! is_int( $candidate_value ) && ! is_float( $candidate_value ) ) ) {
				throw new InvalidArgumentException( 'Cosine vectors must contain only numeric values.' );
			}

			$query_float     = (float) $query_value;
			$candidate_float = (float) $candidate_value;
			if ( ! is_finite( $query_float ) || ! is_finite( $candidate_float ) ) {
				throw new InvalidArgumentException( 'Cosine vectors must contain only finite values.' );
			}

			$dot            += $query_float * $candidate_float;
			$query_norm     += $query_float * $query_float;
			$candidate_norm += $candidate_float * $candidate_float;
		}

		if ( $query_norm <= 0.0 || $candidate_norm <= 0.0 ) {
			throw new InvalidArgumentException( 'Cosine similarity is undefined for zero-norm vectors.' );
		}

		return $dot / ( sqrt( $query_norm ) * sqrt( $candidate_norm ) );
	}

	/**
	 * Static utility only.
	 */
	private function __construct() {
	}
}
