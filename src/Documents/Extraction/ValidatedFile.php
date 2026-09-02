<?php
/**
 * Validated file value.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents\Extraction;

use InvalidArgumentException;

// phpcs:disable WordPress.NamingConventions -- Public record property names follow the approved domain contract.
/**
 * Immutable trusted metadata for a file that has passed validation.
 */
final readonly class ValidatedFile {
	/**
	 * Create a validated file value.
	 *
	 * @param string $path Canonical local path.
	 * @param string $basename File basename.
	 * @param string $extension Lowercase extension without a dot.
	 * @param string $mimeType Server-detected MIME type.
	 * @param int    $size File size in bytes.
	 * @param string $sha256 Lowercase SHA-256 file hash.
	 * @throws InvalidArgumentException When trusted file metadata is invalid.
	 */
	public function __construct(
		public string $path,
		public string $basename,
		public string $extension,
		public string $mimeType,
		public int $size,
		public string $sha256
	) {
		if ( '' === $path || '' === $basename || '' === $extension || '' === $mimeType ) {
			throw new InvalidArgumentException( 'Validated file metadata must not be empty.' );
		}

		if ( $size < 1 ) {
			throw new InvalidArgumentException( 'Validated file size must be at least one byte.' );
		}

		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
			throw new InvalidArgumentException( 'Validated file hash must be a lowercase SHA-256 hexadecimal value.' );
		}
	}
}
// phpcs:enable WordPress.NamingConventions
