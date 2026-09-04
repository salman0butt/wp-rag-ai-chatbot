<?php
/**
 * Portable metadata membership filter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Filter;

use InvalidArgumentException;

/**
 * Strict scalar membership filter.
 */
final class InFilter implements VectorFilter {
	/** Maximum portable values in one membership filter. */
	private const MAX_VALUES = 32;

	/**
	 * Create a membership filter.
	 *
	 * @param string $key Portable metadata key.
	 * @param array  $values Allowed scalar values.
	 * @phpstan-param list<scalar> $values
	 * @throws InvalidArgumentException When filter input is invalid.
	 */
	public function __construct(
		public readonly string $key,
		public readonly array $values
	) {
		FilterValidation::key( $key );
		if ( ! array_is_list( $values ) || array() === $values || count( $values ) > self::MAX_VALUES ) {
			throw new InvalidArgumentException( 'Vector membership filter values are invalid.' );
		}
		foreach ( $values as $value ) {
			FilterValidation::value( $value );
		}
	}

	/**
	 * Determine whether metadata matches.
	 *
	 * @param array<string, scalar> $metadata Portable metadata.
	 */
	public function matches( array $metadata ): bool {
		if ( ! array_key_exists( $this->key, $metadata ) ) {
			return false;
		}

		foreach ( $this->values as $value ) {
			if ( $metadata[ $this->key ] === $value ) {
				return true;
			}
		}

		return false;
	}
}
