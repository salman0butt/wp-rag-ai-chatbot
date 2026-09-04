<?php
/**
 * Managed vector search match.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Managed;

use InvalidArgumentException;

/**
 * Immutable safe projection of one managed search result.
 */
final class ManagedVectorMatch {
	/**
	 * Create one bounded managed search match.
	 *
	 * @param string               $file_id Provider file ID.
	 * @param string               $filename Provider filename.
	 * @param float                $score Provider-native relevance score.
	 * @param array<int, mixed>    $content Untrusted text fragments to validate.
	 * @param array<string, mixed> $attributes Bounded scalar attributes.
	 * @throws InvalidArgumentException When any result field is invalid.
	 */
	public function __construct(
		public readonly string $file_id,
		public readonly string $filename,
		public readonly float $score,
		public readonly array $content,
		public readonly array $attributes
	) {
		if ( 1 !== preg_match( '/^file[-_][A-Za-z0-9_-]{1,191}$/', $file_id ) ) {
			throw new InvalidArgumentException( 'Managed vector file ID is invalid.' );
		}
		if ( '' === trim( $filename ) || strlen( $filename ) > 512 ) {
			throw new InvalidArgumentException( 'Managed vector filename is invalid.' );
		}
		if ( ! is_finite( $score ) || $score < 0.0 || $score > 1.0 ) {
			throw new InvalidArgumentException( 'Managed vector score is invalid.' );
		}
		if ( ! array_is_list( $content ) || count( $content ) > 32 ) {
			throw new InvalidArgumentException( 'Managed vector content is invalid.' );
		}
		foreach ( $content as $text ) {
			if ( ! is_string( $text ) || strlen( $text ) > 65536 ) {
				throw new InvalidArgumentException( 'Managed vector content is invalid.' );
			}
		}
		self::validate_attributes( $attributes );
	}

	/**
	 * Validate the portable OpenAI attribute subset.
	 *
	 * @param array<mixed, mixed> $attributes Attributes to validate.
	 * @throws InvalidArgumentException When any attribute exceeds the portable bounds.
	 */
	public static function validate_attributes( array $attributes ): void {
		if ( count( $attributes ) > 16 ) {
			throw new InvalidArgumentException( 'Managed vector attributes are invalid.' );
		}
		foreach ( $attributes as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key || strlen( $key ) > 64 || preg_match( '/[\x00-\x1F\x7F]/', $key ) ) {
				throw new InvalidArgumentException( 'Managed vector attributes are invalid.' );
			}
			if ( is_string( $value ) ) {
				if ( strlen( $value ) > 512 || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value ) ) {
					throw new InvalidArgumentException( 'Managed vector attributes are invalid.' );
				}
				continue;
			}
			if ( is_int( $value ) || is_bool( $value ) || ( is_float( $value ) && is_finite( $value ) ) ) {
				continue;
			}
			throw new InvalidArgumentException( 'Managed vector attributes are invalid.' );
		}
	}
}
