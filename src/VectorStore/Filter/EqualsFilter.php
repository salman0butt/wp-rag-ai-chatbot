<?php
/**
 * Portable metadata equality filter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Filter;

use InvalidArgumentException;

/**
 * Strict scalar equality filter.
 */
final class EqualsFilter implements VectorFilter {
	/**
	 * Create an equality filter.
	 *
	 * @param string $key Portable metadata key.
	 * @param mixed  $value Portable scalar value.
	 * @phpstan-param scalar $value
	 * @throws InvalidArgumentException When filter input is invalid.
	 */
	public function __construct(
		public readonly string $key,
		public readonly mixed $value
	) {
		FilterValidation::key( $key );
		FilterValidation::value( $value );
	}

	/**
	 * Determine whether metadata matches.
	 *
	 * @param array<string, scalar> $metadata Portable metadata.
	 */
	public function matches( array $metadata ): bool {
		return array_key_exists( $this->key, $metadata ) && $metadata[ $this->key ] === $this->value;
	}
}
