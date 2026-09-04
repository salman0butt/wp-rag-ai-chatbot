<?php
/**
 * Embedding compatibility profile.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Embeddings;

use InvalidArgumentException;

/**
 * Immutable embedding-generation identity.
 */
final class EmbeddingProfile {
	/**
	 * Create an embedding compatibility profile.
	 *
	 * @param string            $provider_id Stable provider ID.
	 * @param string            $model_id Provider model ID.
	 * @param int               $dimensions Vector dimensions.
	 * @param NormalizationMode $normalization Vector normalization mode.
	 * @throws InvalidArgumentException When profile values are invalid.
	 */
	public function __construct(
		public readonly string $provider_id,
		public readonly string $model_id,
		public readonly int $dimensions,
		public readonly NormalizationMode $normalization
	) {
		if ( '' === trim( $provider_id ) || '' === trim( $model_id ) ) {
			throw new InvalidArgumentException( 'Embedding profile provider and model must not be blank.' );
		}
		if ( 1 === preg_match( '/[\x00-\x1F\x7F]/', $provider_id . $model_id ) ) {
			throw new InvalidArgumentException( 'Embedding profile provider and model must not contain control characters.' );
		}
		if ( $dimensions < 1 ) {
			throw new InvalidArgumentException( 'Embedding profile dimensions must be positive.' );
		}
	}
}
