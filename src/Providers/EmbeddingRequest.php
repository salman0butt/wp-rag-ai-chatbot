<?php
/**
 * Normalized embedding request.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Immutable ordered embedding request.
 */
final class EmbeddingRequest {
	/**
	 * @param string      $model Provider model ID.
	 * @param string[]    $inputs Ordered text inputs.
	 * @param int|null    $dimensions Optional requested dimensions.
	 * @throws InvalidArgumentException When request values are invalid.
	 */
	public function __construct(
		public readonly string $model,
		public readonly array $inputs,
		public readonly ?int $dimensions = null
	) {
		if ( '' === trim( $model ) ) {
			throw new InvalidArgumentException( 'Embedding model must not be blank.' );
		}
		if ( array() === $inputs ) {
			throw new InvalidArgumentException( 'Embedding inputs must not be empty.' );
		}
		foreach ( $inputs as $input ) {
			if ( ! is_string( $input ) || '' === trim( $input ) ) {
				throw new InvalidArgumentException( 'Embedding inputs must be non-empty strings.' );
			}
		}
		if ( null !== $dimensions && $dimensions < 1 ) {
			throw new InvalidArgumentException( 'Embedding dimensions must be positive.' );
		}
	}
}
