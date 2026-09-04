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
	/** Maximum portable metadata entries. */
	private const MAX_METADATA_ENTRIES = 32;

	/**
	 * Validated portable metadata.
	 *
	 * @var array<string, scalar>
	 */
	public readonly array $metadata;

	/**
	 * Create a vector match.
	 *
	 * @param string $id Stable record ID.
	 * @param float  $score Comparable adapter score.
	 * @param array  $metadata Untrusted portable metadata.
	 * @phpstan-param array<array-key, mixed> $metadata
	 * @throws InvalidArgumentException When the match is invalid.
	 */
	public function __construct(
		public readonly string $id,
		public readonly float $score,
		array $metadata = array()
	) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,191}$/', $id ) || ! is_finite( $score ) ) {
			throw new InvalidArgumentException( 'Vector match ID and score must be valid.' );
		}
		if ( count( $metadata ) > self::MAX_METADATA_ENTRIES ) {
			throw new InvalidArgumentException( 'Vector match metadata exceeds the portable entry limit.' );
		}
		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $key ) ) {
				throw new InvalidArgumentException( 'Vector match metadata key is invalid.' );
			}
			if ( ! is_scalar( $value ) ) {
				throw new InvalidArgumentException( 'Vector match metadata values must be scalar.' );
			}
			if ( is_string( $value ) && strlen( $value ) > 512 ) {
				throw new InvalidArgumentException( 'Vector match metadata string exceeds the portable length limit.' );
			}
			if ( is_float( $value ) && ! is_finite( $value ) ) {
				throw new InvalidArgumentException( 'Vector match metadata float must be finite.' );
			}
		}

		/**
		 * Validated portable metadata.
		 *
		 * @var array<string, scalar> $metadata
		 */
		$this->metadata = $metadata;
	}
}
