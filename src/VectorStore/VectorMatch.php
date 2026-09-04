<?php
/**
 * Vector search match.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use InvalidArgumentException;

/**
 * Immutable scored vector match.
 */
final class VectorMatch {
	/**
	 * Create a vector match.
	 *
	 * @param string                $id Stable record ID.
	 * @param float                 $score Comparable adapter score.
	 * @param array<string, scalar> $metadata Portable metadata.
	 * @throws InvalidArgumentException When the match is invalid.
	 */
	public function __construct(
		public readonly string $id,
		public readonly float $score,
		public readonly array $metadata = array()
	) {
		if ( '' === trim( $id ) || ! is_finite( $score ) ) {
			throw new InvalidArgumentException( 'Vector match ID and score must be valid.' );
		}
	}
}
