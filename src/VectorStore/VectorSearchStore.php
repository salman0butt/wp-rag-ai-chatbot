<?php
/**
 * Vector search capability contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Optional raw-vector search operation.
 */
interface VectorSearchStore extends VectorStore {
	/**
	 * Search one collection using a compatibility-checked query.
	 *
	 * @param VectorSearchRequest $request Search request.
	 */
	public function search( VectorSearchRequest $request ): VectorSearchResult;
}
