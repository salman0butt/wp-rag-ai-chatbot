<?php
/**
 * Immutable source-to-index planning result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing;

use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;

// phpcs:disable WordPress.NamingConventions -- Public result properties follow the approved camelCase domain contract.
/**
 * Current chunk evidence plus the pure incremental plan for one document.
 */
final readonly class DocumentIndexResult {
	/**
	 * Create one immutable document-index result.
	 *
	 * @param string                  $normalizedContent Canonical normalized document text.
	 * @param array<int, ChunkRecord> $chunks Ordered chunks before deduplication.
	 * @param array<int, ChunkRecord> $canonicalChunks Ordered canonical chunks after deduplication.
	 * @param array<string, string>   $duplicateAliases Duplicate chunk key => canonical chunk key.
	 * @param IndexPlan               $indexPlan Pure incremental index plan.
	 */
	public function __construct(
		public string $normalizedContent,
		public array $chunks,
		public array $canonicalChunks,
		public array $duplicateAliases,
		public IndexPlan $indexPlan
	) {
	}
}
// phpcs:enable WordPress.NamingConventions
