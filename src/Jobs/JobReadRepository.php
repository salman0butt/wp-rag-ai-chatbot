<?php
/**
 * Read-only background job inspection contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use WpRagAiChatbot\Core\PagedResult;

// phpcs:disable WordPress.NamingConventions -- Repository API follows the approved domain contract.
/**
 * Exposes bounded persisted job reads without widening the M09 mutation contract.
 */
interface JobReadRepository {
	/**
	 * Find one persisted job by stable key.
	 *
	 * @param string $job_key Stable job identity.
	 */
	public function findByKey( string $job_key ): ?JobRecord;

	/**
	 * Return one bounded page of persisted jobs.
	 *
	 * @param int $page One-based page.
	 * @param int $perPage Requested page size.
	 */
	public function paginate( int $page, int $perPage ): PagedResult;
}
// phpcs:enable WordPress.NamingConventions
