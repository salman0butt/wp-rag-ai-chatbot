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
	 * @param array<int, string>   $headingPath Ordered heading lineage.
	 * @param array<string, mixed> $sourceMetadata Copied source metadata.
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
