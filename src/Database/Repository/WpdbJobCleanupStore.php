<?php
/**
 * WordPress database-backed terminal job cleanup store.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use DateTimeImmutable;
use DateTimeZone;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Jobs\JobCleanupStore;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobStatus;

/**
 * Deletes bounded terminal queue history using prepared site-scoped SQL.
 */
final class WpdbJobCleanupStore implements JobCleanupStore {
	private const MAX_LIMIT = 500;

	/**
	 * Create the cleanup store.
	 *
	 * @param Connection $connection Database connection.
	 * @param TableNames $tables Site-scoped table names.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Delete terminal jobs before the cutoff in deterministic bounded order.
	 *
	 * @param DateTimeImmutable $before Cleanup cutoff.
	 * @param int               $limit Maximum rows to delete.
	 * @throws JobQueueException When the storage bound is invalid or the query fails.
	 */
	public function delete_terminal_before( DateTimeImmutable $before, int $limit ): int {
		if ( $limit < 1 || $limit > self::MAX_LIMIT ) {
			throw new JobQueueException( 'Job cleanup storage limit must be between 1 and 500.' );
		}

		$cutoff = $before->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$sql    = $this->connection->prepare(
			'DELETE FROM %i WHERE status IN (%s, %s, %s) AND completed_at IS NOT NULL AND completed_at < %s ORDER BY completed_at ASC, id ASC LIMIT %d',
			$this->tables->jobs(),
			JobStatus::SUCCEEDED->value,
			JobStatus::FAILED->value,
			JobStatus::CANCELLED->value,
			$cutoff,
			$limit
		);

		$deleted = $this->connection->query( $sql );
		if ( ! is_int( $deleted ) || $deleted < 0 || $deleted > $limit ) {
			throw new JobQueueException( 'Job cleanup query failed or returned an invalid affected-row count.' );
		}
		return $deleted;
	}
}
