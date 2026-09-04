<?php
/**
 * Deterministic incremental index planner.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Planning;

use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicationResult;

// phpcs:disable WordPress.NamingConventions -- ChunkRecord properties follow the approved camelCase domain contract.
/**
 * Compares previous and current canonical chunks without side effects.
 */
final class IncrementalIndexPlanner {
	/**
	 * Build the minimum deterministic index work required for current chunks.
	 *
	 * @param array<int, ChunkRecord>    $previousChunks Previous canonical chunks.
	 * @param ChunkDeduplicationResult $current Current deduplicated chunks.
	 */
	public function plan( array $previousChunks, ChunkDeduplicationResult $current ): IndexPlan {
		$previous_by_key = array();
		foreach ( $previousChunks as $chunk ) {
			$previous_by_key[ $chunk->chunkKey ] = $chunk;
		}

		$current_by_key = array();
		$upsert         = array();
		$unchanged      = array();

		foreach ( $current->canonicalChunks as $chunk ) {
			$current_by_key[ $chunk->chunkKey ] = true;

			$previous = $previous_by_key[ $chunk->chunkKey ] ?? null;
			if ( null !== $previous && $this->isUnchanged( $previous, $chunk ) ) {
				$unchanged[] = $chunk;
				continue;
			}

			$upsert[] = $chunk;
		}

		$delete_keys = array();
		foreach ( array_keys( $previous_by_key ) as $chunk_key ) {
			if ( ! isset( $current_by_key[ $chunk_key ] ) ) {
				$delete_keys[] = $chunk_key;
			}
		}

		usort( $upsert, array( $this, 'compareChunks' ) );
		usort( $unchanged, array( $this, 'compareChunks' ) );
		sort( $delete_keys, SORT_STRING );

		$duplicate_aliases = $current->duplicateAliases;
		ksort( $duplicate_aliases, SORT_STRING );

		return new IndexPlan( $upsert, $delete_keys, $unchanged, $duplicate_aliases );
	}

	/**
	 * Determine whether an existing key remains compatibility-safe and reusable.
	 *
	 * @param ChunkRecord $previous Previous canonical chunk.
	 * @param ChunkRecord $current Current canonical chunk.
	 */
	private function isUnchanged( ChunkRecord $previous, ChunkRecord $current ): bool {
		return $previous->contentHash === $current->contentHash
			&& $previous->chunkingFingerprint === $current->chunkingFingerprint
			&& $previous->embeddingCompatibilityKey === $current->embeddingCompatibilityKey;
	}

	/**
	 * Compare chunks by deterministic sequence and stable chunk key.
	 *
	 * @param ChunkRecord $left Left chunk.
	 * @param ChunkRecord $right Right chunk.
	 */
	private function compareChunks( ChunkRecord $left, ChunkRecord $right ): int {
		if ( $left->sequence !== $right->sequence ) {
			return $left->sequence <=> $right->sequence;
		}

		return strcmp( $left->chunkKey, $right->chunkKey );
	}
}
// phpcs:enable WordPress.NamingConventions
