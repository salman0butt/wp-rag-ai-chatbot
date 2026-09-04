<?php
/**
 * Normalized embedding result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Immutable normalized provider embedding response.
 */
final class EmbeddingResult {
	/**
	 * Create a normalized embedding result.
	 *
	 * @param string                  $provider_id Stable provider ID.
	 * @param string                  $model Provider model ID.
	 * @param array<array-key, mixed> $vectors Ordered raw vectors.
	 * @param EmbeddingUsage          $usage Normalized usage.
	 * @throws InvalidArgumentException When result metadata is invalid.
	 */
	public function __construct(
		public readonly string $provider_id,
		public readonly string $model,
		public readonly array $vectors,
		public readonly EmbeddingUsage $usage
	) {
		if ( '' === trim( $provider_id ) || '' === trim( $model ) ) {
			throw new InvalidArgumentException( 'Embedding result provider and model must not be blank.' );
		}
		if ( array() === $vectors ) {
			throw new InvalidArgumentException( 'Embedding result must contain vectors.' );
		}
		if ( ! array_is_list( $vectors ) ) {
			throw new InvalidArgumentException( 'Embedding result vectors must be an ordered list.' );
		}
		foreach ( $vectors as $vector ) {
			if ( ! $vector instanceof EmbeddingVector ) {
				throw new InvalidArgumentException( 'Embedding result vectors must be EmbeddingVector instances.' );
			}
		}
	}
}
