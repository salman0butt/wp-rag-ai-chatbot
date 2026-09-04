<?php
/**
 * Immutable chunk record.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Chunking;

use InvalidArgumentException;

// phpcs:disable WordPress.NamingConventions -- Public record property names follow the approved domain contract.
/**
 * One deterministic chunk with copied document lineage.
 */
final readonly class ChunkRecord {
	/**
	 * Create a chunk record.
	 *
	 * @param string               $chunkKey Stable chunk identifier.
	 * @param string               $documentKey Owning document key.
	 * @param int                  $sourceId Owning source identifier.
	 * @param string               $documentType Document type.
	 * @param string               $title Document title.
	 * @param string|null          $canonicalUrl Canonical document URL.
	 * @param string               $content Chunk content.
	 * @param string               $contentHash Stable chunk content hash.
	 * @param string|null          $sourceVersion Upstream source version.
	 * @param string               $documentContentHash Owning document content hash.
	 * @param string|null          $language Document language when known.
	 * @param string               $visibility Access visibility.
	 * @param int                  $sequence Deterministic chunk sequence.
	 * @param string|null          $parentChunkKey Structural parent key.
	 * @param array<int, string>   $headingPath Ordered heading lineage.
	 * @param int                  $tokenCount Deterministic lexical-unit count.
	 * @param string               $chunkingVersion Chunking algorithm version.
	 * @param string               $chunkingFingerprint Chunking compatibility fingerprint.
	 * @param string|null          $embeddingCompatibilityKey Optional embedding compatibility boundary.
	 * @param array<string, mixed> $sourceMetadata Copied source metadata.
	 * @throws InvalidArgumentException When record invariants are invalid.
	 */
	public function __construct(
		public string $chunkKey,
		public string $documentKey,
		public int $sourceId,
		public string $documentType,
		public string $title,
		public ?string $canonicalUrl,
		public string $content,
		public string $contentHash,
		public ?string $sourceVersion,
		public string $documentContentHash,
		public ?string $language,
		public string $visibility,
		public int $sequence,
		public ?string $parentChunkKey,
		public array $headingPath,
		public int $tokenCount,
		public string $chunkingVersion,
		public string $chunkingFingerprint,
		public ?string $embeddingCompatibilityKey,
		public array $sourceMetadata
	) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $chunkKey ) ) {
			throw new InvalidArgumentException( 'Chunk key must be a lowercase SHA-256 hexadecimal value.' );
		}
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $contentHash ) ) {
			throw new InvalidArgumentException( 'Chunk content hash must be a lowercase SHA-256 hexadecimal value.' );
		}
		if ( $sequence < 0 ) {
			throw new InvalidArgumentException( 'Chunk sequence must be non-negative.' );
		}
		if ( $tokenCount < 1 ) {
			throw new InvalidArgumentException( 'Chunk token count must be positive.' );
		}
	}
}
// phpcs:enable WordPress.NamingConventions
