<?php
/**
 * Canonical persisted chunk lookup contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

/**
 * Resolves one persisted canonical chunk inside an explicit collection boundary.
 */
interface ChunkLookupStore {
	/**
	 * Find one canonical chunk by collection and stable chunk key.
	 *
	 * @param string $collection_id Explicit persisted collection scope.
	 * @param string $chunk_key Stable lowercase SHA-256 chunk key.
	 */
	public function find_chunk( string $collection_id, string $chunk_key ): ?ChunkSearchRecord;
}
