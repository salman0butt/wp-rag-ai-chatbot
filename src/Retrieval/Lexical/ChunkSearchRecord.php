<?php
/**
 * Searchable chunk projection record.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use InvalidArgumentException;

/**
 * Immutable bounded chunk data stored for lexical retrieval.
 */
final readonly class ChunkSearchRecord {
	private const MAX_METADATA_ENTRIES = 32;

	/**
	 * Create one validated search projection record.
	 *
	 * @param string      $chunk_key Stable chunk key.
	 * @param string      $document_key Owning document key.
	 * @param int         $source_id Owning source identifier.
	 * @param string      $document_type Document type.
	 * @param string      $title Document title.
	 * @param string|null $canonical_url Canonical document URL.
	 * @param string      $content Chunk content.
	 * @param string      $content_hash Stable chunk content hash.
	 * @param string|null $language Optional language.
	 * @param string      $visibility Trusted visibility classification.
	 * @param int         $sequence Deterministic chunk sequence.
	 * @param array       $metadata Safe portable source metadata.
	 * @phpstan-param array<array-key, mixed> $metadata
	 * @throws InvalidArgumentException When projection data exceeds hard bounds.
	 */
	public function __construct(
		public string $chunk_key,
		public string $document_key,
		public int $source_id,
		public string $document_type,
		public string $title,
		public ?string $canonical_url,
		public string $content,
		public string $content_hash,
		public ?string $language,
		public string $visibility,
		public int $sequence,
		public array $metadata = array()
	) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $chunk_key ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $content_hash ) ) {
			throw new InvalidArgumentException( 'Chunk-search hashes must be lowercase SHA-256 values.' );
		}
		if ( '' === $document_key || strlen( $document_key ) > 191 || $source_id < 1 || '' === $document_type || strlen( $document_type ) > 100 ) {
			throw new InvalidArgumentException( 'Chunk-search lineage is invalid.' );
		}
		if ( '' === $visibility || strlen( $visibility ) > 32 || $sequence < 0 || ( null !== $language && strlen( $language ) > 35 ) ) {
			throw new InvalidArgumentException( 'Chunk-search scope is invalid.' );
		}
		if ( count( $metadata ) > self::MAX_METADATA_ENTRIES ) {
			throw new InvalidArgumentException( 'Chunk-search metadata exceeds the portable entry limit.' );
		}
		foreach ( $metadata as $key => $value ) {
			if ( ! is_string( $key ) || 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $key ) ) {
				throw new InvalidArgumentException( 'Chunk-search metadata key is invalid.' );
			}
			if ( ! is_scalar( $value ) || ( is_string( $value ) && strlen( $value ) > 512 ) || ( is_float( $value ) && ! is_finite( $value ) ) ) {
				throw new InvalidArgumentException( 'Chunk-search metadata value is invalid.' );
			}
		}
	}
}
