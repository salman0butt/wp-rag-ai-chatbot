<?php
/**
 * Immutable chunk deduplication result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Dedup;

use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;

// phpcs:disable WordPress.NamingConventions -- Public result properties follow the approved camelCase domain contract.
/**
 * Ordered canonical chunks plus duplicate-to-canonical aliases.
 */
final readonly class ChunkDeduplicationResult {
	/**
	 * Create one immutable deduplication result.
	 *
	 * @param array<int, ChunkRecord> $canonicalChunks Ordered canonical chunks.
	 * @param array<string, string>   $duplicateAliases Duplicate chunk key => canonical chunk key.
	 */
	public function __construct(
		public array $canonicalChunks,
		public array $duplicateAliases
	) {
	}
}
// phpcs:enable WordPress.NamingConventions
