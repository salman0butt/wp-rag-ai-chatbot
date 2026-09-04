<?php
/**
 * Immutable incremental index plan.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Planning;

use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;

// phpcs:disable WordPress.NamingConventions -- Public plan properties follow the approved camelCase domain contract.
/**
 * Pure planning result for later embedding/vector execution.
 */
final readonly class IndexPlan {
	/**
	 * Create one immutable index plan.
	 *
	 * @param array<int, ChunkRecord> $upsert Current chunks requiring embedding/index work.
	 * @param array<int, ChunkRecord> $metadataRefresh Current chunks requiring lineage metadata refresh without re-embedding.
	 * @param array<int, string>      $deleteKeys Previous canonical keys to remove.
	 * @param array<int, ChunkRecord> $unchanged Current chunks requiring no index work.
	 * @param array<string, string>   $duplicateAliases Duplicate chunk key => canonical chunk key.
	 */
	public function __construct(
		public array $upsert,
		public array $metadataRefresh,
		public array $deleteKeys,
		public array $unchanged,
		public array $duplicateAliases
	) {
	}
}
// phpcs:enable WordPress.NamingConventions
