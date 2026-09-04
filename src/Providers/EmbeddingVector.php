<?php
/**
 * Normalized embedding vector.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Immutable indexed vector with finite numeric values.
 */
final class EmbeddingVector {
	/**
	 * @param int                  $index Original zero-based input index.
	 * @param array<int, int|float> $values Ordered vector values.
	 * @throws InvalidArgumentException When index or values are invalid.
	 */
	public function __construct(
		public readonly int $index,
		public readonly array $values
	) {
		if ( $index < 0 ) {
			throw new InvalidArgumentException( 'Embedding vector index must not be negative.' );
		}
		if ( array() === $values ) {
			throw new InvalidArgumentException( 'Embedding vector must not be empty.' );
		}
		foreach ( $values as $value ) {
			if ( ! is_int( $value ) && ! is_float( $value ) ) {
				throw new InvalidArgumentException( 'Embedding vector values must be numeric.' );
			}
			if ( ! is_finite( (float) $value ) ) {
				throw new InvalidArgumentException( 'Embedding vector values must be finite.' );
			}
		}
	}
}
