<?php
/**
 * Database migration runner.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Runs pending schema migrations in version order under an advisory lock.
 */
final class MigrationRunner {
	/**
	 * Create the migration runner.
	 *
	 * @param Connection         $connection Database connection.
	 * @param SchemaVersionStore $versions Schema-version store.
	 * @param MigrationLock      $lock Migration lock.
	 * @param Migration[]        $migrations Ordered or unordered migration set.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly SchemaVersionStore $versions,
		private readonly MigrationLock $lock,
		private array $migrations
	) {
		usort(
			$this->migrations,
			static fn ( Migration $left, Migration $right ): int => $left->version() <=> $right->version()
		);
	}

	/**
	 * Run every pending migration once in version order.
	 */
	public function run(): MigrationStatus {
		$current = $this->versions->current();

		if ( $current >= DatabaseSchema::VERSION ) {
			return MigrationStatus::UP_TO_DATE;
		}

		if ( ! $this->lock->acquire() ) {
			return MigrationStatus::LOCKED;
		}

		try {
			foreach ( $this->migrations as $migration ) {
				if ( $migration->version() <= $current ) {
					continue;
				}

				$migration->up( $this->connection );
				$this->versions->save( $migration->version() );
				$current = $migration->version();
			}
		} finally {
			$this->lock->release();
		}

		return MigrationStatus::MIGRATED;
	}
}
