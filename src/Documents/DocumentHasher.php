<?php
/**
 * Deterministic document hashing.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents;

use JsonException;

/**
 * Produces stable SHA-256 hashes from canonical document payloads.
 */
final class DocumentHasher {
	/**
	 * Hash a document payload after recursively canonicalizing associative keys.
	 *
	 * @param array<mixed> $payload Document payload.
	 * @throws JsonException When the canonical payload cannot be encoded.
	 */
	public static function hash( array $payload ): string {
		$canonical = self::canonicalize( $payload );
		$json      = json_encode(
			$canonical,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
		);

		return hash( 'sha256', $json );
	}

	/**
	 * Recursively sort associative arrays while preserving list order.
	 *
	 * @param mixed $value Value to canonicalize.
	 * @return mixed
	 */
	private static function canonicalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array_is_list( $value ) ) {
			return array_map( self::canonicalize( ... ), $value );
		}

		ksort( $value, SORT_STRING );
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}

		return $value;
	}
}
