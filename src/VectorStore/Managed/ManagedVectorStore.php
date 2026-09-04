<?php
/**
 * Managed vector-store operation contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Managed;

use WpRagAiChatbot\VectorStore\VectorStore;
use WpRagAiChatbot\VectorStore\VectorWriteResult;

/**
 * Exposes managed file ingestion/deletion and provider-managed text search.
 */
interface ManagedVectorStore extends VectorStore {
	/**
	 * Attach an existing provider file to the managed vector store.
	 *
	 * @param string               $file_id Provider file ID.
	 * @param array<string, mixed> $attributes Bounded searchable file attributes.
	 */
	public function attach_file( string $file_id, array $attributes = array() ): VectorWriteResult;

	/**
	 * Detach one provider file from the managed vector store.
	 *
	 * @param string $file_id Provider file ID.
	 */
	public function delete_file( string $file_id ): VectorWriteResult;

	/**
	 * Search provider-managed content using text, not caller-supplied raw vectors.
	 *
	 * @param string $query Text query.
	 * @param int    $max_results Maximum results to return.
	 */
	public function managed_search( string $query, int $max_results = 10 ): ManagedVectorSearchResult;
}
