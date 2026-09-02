<?php
/**
 * Knowledge source repository contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge;

use WpRagAiChatbot\Core\PagedResult;

// phpcs:disable WordPress.NamingConventions -- Public repository methods follow the approved domain contract.
/**
 * Persists and pages knowledge sources.
 */
interface KnowledgeSourceRepository {
	/**
	 * Insert or update a source record.
	 *
	 * @param KnowledgeSourceRecord $record Source record.
	 */
	public function save( KnowledgeSourceRecord $record ): KnowledgeSourceRecord;

	/**
	 * Find a source by persisted identifier.
	 *
	 * @param int $id Persisted identifier.
	 */
	public function findById( int $id ): ?KnowledgeSourceRecord;

	/**
	 * Find a source by stable source key.
	 *
	 * @param string $source_key Stable source key.
	 */
	public function findByKey( string $source_key ): ?KnowledgeSourceRecord;

	/**
	 * Return a bounded page of sources.
	 *
	 * @param int $page One-based page.
	 * @param int $per_page Requested page size.
	 */
	public function paginate( int $page = 1, int $per_page = 20 ): PagedResult;

	/**
	 * Delete a source by persisted identifier.
	 *
	 * @param int $id Persisted identifier.
	 */
	public function delete( int $id ): void;
}
// phpcs:enable WordPress.NamingConventions
