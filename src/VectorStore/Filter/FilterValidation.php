<?php
/**
 * Portable vector-filter validation helpers.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Filter;

use InvalidArgumentException;

/**
 * Central validation for portable metadata filter primitives.
 */
final class FilterValidation {
	/**
	 * Validate a portable metadata key.
	 *
	 * @param string $key Metadata key.
	 * @throws InvalidArgumentException When the key is invalid.
	 */
	public static function key( string $key ): void {
		if ( 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $key ) ) {
			throw new InvalidArgumentException( 'Vector filter key is invalid.' );
		}
	}

	/**
	 * Validate a portable scalar value.
	 *
	 * @param mixed $value Candidate scalar value.
	 * @throws InvalidArgumentException When the value is not portable.
	 */
	public static function value( mixed $value ): void {
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) && ! is_bool( $value ) ) {
			throw new InvalidArgumentException( 'Vector filter value must be a portable scalar.' );
		}
		if ( is_string( $value ) && strlen( $value ) > 512 ) {
			throw new InvalidArgumentException( 'Vector filter string exceeds the portable length limit.' );
		}
		if ( is_float( $value ) && ! is_finite( $value ) ) {
			throw new InvalidArgumentException( 'Vector filter float must be finite.' );
		}
	}
}
