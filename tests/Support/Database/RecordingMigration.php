<?php
/**
 * Recording migration fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Migration;

/**
 * Migration that records its execution version.
 */
final class RecordingMigration implements Migration {
	/**
	 * Create a recording migration.
	 *
	 * @param int          $migration_version Migration version.
	 * @param MigrationLog $log Shared execution log.
	 */
	public function __construct(
		private readonly int $migration_version,
		private readonly MigrationLog $log
	) {
	}

	/**
	 * Get migration version.
	 */
	public function version(): int {
		return $this->migration_version;
	}

	/**
	 * Record execution.
	 *
	 * @param Connection $connection Unused test connection.
	 */
	public function up( Connection $connection ): void {
		$this->log->versions[] = $this->migration_version;
	}
}
