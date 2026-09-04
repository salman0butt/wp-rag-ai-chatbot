<?php
/**
 * Vector record value object.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use InvalidArgumentException;

/**
 * Immutable collection-scoped vector record.
 */
final class VectorRecord {
	/** Maximum portable metadata entries. */
	private const MAX_METADATA_ENTRIES = 32;

	/**
	 * Validated dense vector.
	 *
	 * @var list<int|float>
	 */
	public readonly array $values;

	/**
	 * Validated portable metadata.
	 *
	 * @var array<string, scalar>
	 */
	public readonly array $metadata;

	/**
	 * Create a vector record.
	 *
	 * @param VectorCollection $collection Collection boundary.
	 * @param string           $id Stable record ID.
	 * @param array            $values Untrusted dense vector values.
	 * @param string           $compatibility_fingerprint Compatibility fingerprint.
	 * @param array            $metadata Untrusted metadata.
	 * @phpstan-param array<array-key, mixed> $values
	 * @phpstan-param array<array-key, mixed> $metadata
	 * @throws InvalidArgumentException When record data is invalid or incompatible.
	 */
	public function __construct(
		public readonly VectorCollection $collection,
		public readonly string $id,
		array $values,
		public readonly string $compatibility_fingerprint,
		array $metadata = array()
	) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,191}$/', $id ) ) {
			throw new InvalidArgumentException( 'Vector record ID is invalid.' );
		}
		self::validate_vector( $values, $collection->profile->embedding->dimensions );
		if ( ! hash_equals( $collection->profile->fingerprint(), $compatibility_fingerprint ) ) {
			throw new InvalidArgumentException( 'Vector record compatibility fingerprint does not match its collection.' );
		}
		self::validate_metadata( $metadata );

		/**
		 * Validated dense vector values.
		 *
		 * @var list<int|float> $values
		 */
		$this->values = $values;

		/**
		 * Validated portable metadata.
		 *
		 * @var array<string, scalar> $metadata
		 */
		$this->metadata = $metadata;
	}

	/**
	 * Validate a dense ordered finite vector.
	 *
	 * @param array $values Dense vector.
	 * @param int   $dimensions Expected dimensions.
	 * @phpstan-param array<array-key, mixed> $values
	 * @throws InvalidArgumentException When vector data is invalid.
	 */
	private static function validate_vector( array $values, int $dimensions ): void {
		if ( ! array_is_list( $values ) || count( $values ) !== $dimensions ) {
			throw new InvalidArgumentException( 'Vector dimensions do not match the collection profile.' );
		}
		foreach ( $values as $value ) {
			if ( ! is_int( $value ) && ! is_float( $value ) ) {
				throw new InvalidArgumentException( 'Vector values must be numeric.' );
			}
			if ( ! is_finite( (float) $value ) ) {
				throw new InvalidArgumentException( 'Vector values must be finite.' );
			}
		}
	}

	/**
	 * Validate portable scalar metadata.
	 *
	 * @param array $metadata Metadata values.
	 * @phpstan-param array<array-key, mixed> $metadata
	 * @throws InvalidArgumentException When metadata exceeds portable bounds.
	 */
	private static function validate_metadata( array $metadata ): void {
		if ( count( $metadata ) > self::MAX_METADATA_ENTRIES ) {
			throw new InvalidArgumentException( 'Vector metadata exceeds the portable entry limit.' );
		}
		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $key ) ) {
				throw new InvalidArgumentException( 'Vector metadata key is invalid.' );
			}
			if ( ! is_scalar( $value ) ) {
				throw new InvalidArgumentException( 'Vector metadata values must be scalar.' );
			}
			if ( is_string( $value ) && strlen( $value ) > 512 ) {
				throw new InvalidArgumentException( 'Vector metadata string exceeds the portable length limit.' );
			}
			if ( is_float( $value ) && ! is_finite( $value ) ) {
				throw new InvalidArgumentException( 'Vector metadata float must be finite.' );
			}
		}
	}
}
