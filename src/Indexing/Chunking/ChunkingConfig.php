<?php
/**
 * Immutable deterministic chunking configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Chunking;

use InvalidArgumentException;
use WpRagAiChatbot\Documents\DocumentHasher;

// phpcs:disable WordPress.NamingConventions -- Public configuration property names follow the approved domain contract.
/**
 * Validated provider-independent chunk-budget configuration.
 */
final readonly class ChunkingConfig {
	/**
	 * Create chunking configuration.
	 *
	 * @param int         $maxTokens Maximum lexical units per chunk.
	 * @param int         $overlapTokens Maximum lexical units copied from the previous sibling chunk.
	 * @param string      $chunkingVersion Stable chunking algorithm version.
	 * @param string|null $embeddingCompatibilityKey Optional embedding compatibility boundary.
	 * @throws InvalidArgumentException When configuration bounds are invalid.
	 */
	public function __construct(
		public int $maxTokens = 512,
		public int $overlapTokens = 64,
		public string $chunkingVersion = 'm07-v1',
		public ?string $embeddingCompatibilityKey = null
	) {
		if ( $maxTokens < 32 || $maxTokens > 4096 ) {
			throw new InvalidArgumentException( 'Maximum tokens must be between 32 and 4096.' );
		}

		if ( $overlapTokens < 0 || ( $overlapTokens * 4 ) > $maxTokens ) {
			throw new InvalidArgumentException( 'Overlap tokens must be non-negative and no more than 25 percent of maximum tokens.' );
		}

		if ( '' === trim( $chunkingVersion ) ) {
			throw new InvalidArgumentException( 'Chunking version must not be blank.' );
		}

		if ( null !== $embeddingCompatibilityKey && '' === trim( $embeddingCompatibilityKey ) ) {
			throw new InvalidArgumentException( 'Embedding compatibility key must be null or non-blank.' );
		}
	}

	/**
	 * Return a stable SHA-256 fingerprint for all chunking compatibility inputs.
	 */
	public function fingerprint(): string {
		return DocumentHasher::hash(
			array(
				'max_tokens'                  => $this->maxTokens,
				'overlap_tokens'              => $this->overlapTokens,
				'chunking_version'            => $this->chunkingVersion,
				'embedding_compatibility_key' => $this->embeddingCompatibilityKey,
			)
		);
	}
}
// phpcs:enable WordPress.NamingConventions
