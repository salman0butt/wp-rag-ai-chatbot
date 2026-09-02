<?php
/**
 * Document repository contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Documents;

use WpRagAiChatbot\Core\PagedResult;

// phpcs:disable WordPress.NamingConventions -- Public repository methods follow the approved domain contract.
/**
 * Persists and pages normalized documents.
 */
interface DocumentRepository {
	/**
	 * Insert or update a document record.
	 */
	public function save( DocumentRecord $record ): DocumentRecord;

	/**
	 * Find a document by stable document key.
	 */
	public function findByKey( string $document_key ): ?DocumentRecord;

	/**
	 * Return a bounded page belonging to one source.
	 */
	public function paginateBySource( int $source_id, int $page = 1, int $per_page = 20 ): PagedResult;

	/**
	 * Delete all documents belonging to one source.
	 */
	public function deleteBySource( int $source_id ): int;
}
// phpcs:enable WordPress.NamingConventions
