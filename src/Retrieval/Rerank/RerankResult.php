<?php
/**
 * Validated reranker output contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Rerank;

use InvalidArgumentException;

/**
 * Immutable ordered candidate score map returned by a reranker.
 */
final readonly class RerankResult {
	/**
	 * Validated ordered candidate scores.
	 *
	 * @var array<string, float>
	 */
	public array $scores;

	/**
	 * Create validated reranker output.
	 *
	 * @param array $scores Ordered candidate ID to finite score map.
	 * @phpstan-param array<array-key, mixed> $scores
	 * @throws InvalidArgumentException When an identifier or score is invalid.
	 */
	public function __construct( array $scores ) {
		$validated = array();

		foreach ( $scores as $chunk_id => $score ) {
			if (
				! is_string( $chunk_id ) ||
				'' === trim( $chunk_id ) ||
				( ! is_int( $score ) && ! is_float( $score ) ) ||
				! is_finite( (float) $score )
			) {
				throw new InvalidArgumentException( 'Rerank result is invalid.' );
			}
			$validated[ $chunk_id ] = (float) $score;
		}

		$this->scores = $validated;
	}
}
