<?php
/**
 * Compatibility-safe deterministic chunk deduplicator.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Dedup;

use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Normalization\ContentNormalizer;

// phpcs:disable WordPress.NamingConventions -- ChunkRecord properties follow the approved camelCase domain contract.
/**
 * Removes compatible duplicate chunks while preserving deterministic aliases.
 */
final class ChunkDeduplicator {
	/**
	 * Deduplicate chunks without mutating caller-owned records.
	 *
	 * The lowest deterministic sequence wins for each compatibility fingerprint.
	 * Equal sequences use the stable chunk key as the final deterministic tie-breaker.
	 *
	 * @param array<int, ChunkRecord> $chunks Final chunks.
	 */
	public function deduplicate( array $chunks ): ChunkDeduplicationResult {
		$canonical_by_fingerprint = array();

		foreach ( $chunks as $chunk ) {
			$fingerprint = $this->fingerprint( $chunk );
			$canonical   = $canonical_by_fingerprint[ $fingerprint ] ?? null;

			if ( null === $canonical || $this->comesBefore( $chunk, $canonical ) ) {
				$canonical_by_fingerprint[ $fingerprint ] = $chunk;
			}
		}

		$canonical_chunks   = array();
		$duplicate_aliases  = array();
		$emitted_canonicals = array();

		foreach ( $chunks as $chunk ) {
			$fingerprint = $this->fingerprint( $chunk );
			$canonical   = $canonical_by_fingerprint[ $fingerprint ];

			if ( ! isset( $emitted_canonicals[ $fingerprint ] ) ) {
				$canonical_chunks[] = $canonical;

				$emitted_canonicals[ $fingerprint ] = true;
			}

			if ( $chunk->chunkKey !== $canonical->chunkKey ) {
				$duplicate_aliases[ $chunk->chunkKey ] = $canonical->chunkKey;
			}
		}

		usort( $canonical_chunks, array( $this, 'compareChunks' ) );
		ksort( $duplicate_aliases, SORT_STRING );

		return new ChunkDeduplicationResult( $canonical_chunks, $duplicate_aliases );
	}

	/**
	 * Build the privacy- and embedding-compatible deduplication fingerprint.
	 *
	 * @param ChunkRecord $chunk Final immutable chunk.
	 */
	private function fingerprint( ChunkRecord $chunk ): string {
		return DocumentHasher::hash(
			array(
				'content'                     => ContentNormalizer::normalize( $chunk->content ),
				'language'                    => $chunk->language,
				'visibility'                  => $chunk->visibility,
				'embedding_compatibility_key' => $chunk->embeddingCompatibilityKey,
			)
		);
	}

	/**
	 * Determine deterministic canonical precedence.
	 *
	 * @param ChunkRecord $candidate Candidate canonical record.
	 * @param ChunkRecord $canonical Current canonical record.
	 */
	private function comesBefore( ChunkRecord $candidate, ChunkRecord $canonical ): bool {
		return $this->compareChunks( $candidate, $canonical ) < 0;
	}

	/**
	 * Compare chunks by deterministic sequence and stable chunk key.
	 *
	 * @param ChunkRecord $left Left record.
	 * @param ChunkRecord $right Right record.
	 */
	private function compareChunks( ChunkRecord $left, ChunkRecord $right ): int {
		if ( $left->sequence !== $right->sequence ) {
			return $left->sequence <=> $right->sequence;
		}

		return strcmp( $left->chunkKey, $right->chunkKey );
	}
}
// phpcs:enable WordPress.NamingConventions
