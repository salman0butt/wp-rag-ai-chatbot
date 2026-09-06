<?php
/**
 * Chunk-search projection contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

/**
 * Durable local projection used by lexical retrieval.
 */
interface ChunkSearchStore {
	/**
	 * Replace all projected chunks for one collection/document scope.
	 *
	 * @param string            $collection_id Collection scope.
	 * @param string            $document_key Owning document key.
	 * @param ChunkSearchRecord ...$chunks Replacement chunks.
	 */
	public function replace_document_chunks( string $collection_id, string $document_key, ChunkSearchRecord ...$chunks ): void;

	/**
	 * Delete all projected chunks for one collection/document scope.
	 *
	 * @param string $collection_id Collection scope.
	 * @param string $document_key Owning document key.
	 */
	public function delete_document( string $collection_id, string $document_key ): void;

	/**
	 * Search the bounded local projection.
	 *
	 * @param LexicalSearchRequest $request Trusted bounded search request.
	 * @return LexicalSearchMatch[]
	 */
	public function search( LexicalSearchRequest $request ): array;
}
