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
	 * Deduplicate ordered chunks without mutating caller-owned records.
	 *
	 * @param array<int, ChunkRecord> $chunks Ordered final chunks.
	 */
	public function deduplicate( array $chunks ): ChunkDeduplicationResult {
		$canonical_chunks  = array();
		$duplicate_aliases = array();
		$canonical_keys    = array();

		foreach ( $chunks as $chunk ) {
			$fingerprint = DocumentHasher::hash(
				array(
					'content'                     => ContentNormalizer::normalize( $chunk->content ),
					'language'                    => $chunk->language,
					'visibility'                  => $chunk->visibility,
					'embedding_compatibility_key' => $chunk->embeddingCompatibilityKey,
				)
			);

			if ( isset( $canonical_keys[ $fingerprint ] ) ) {
				$duplicate_aliases[ $chunk->chunkKey ] = $canonical_keys[ $fingerprint ];
				continue;
			}

			$canonical_keys[ $fingerprint ] = $chunk->chunkKey;
			$canonical_chunks[]             = $chunk;
		}

		return new ChunkDeduplicationResult( $canonical_chunks, $duplicate_aliases );
	}
}
// phpcs:enable WordPress.NamingConventions
