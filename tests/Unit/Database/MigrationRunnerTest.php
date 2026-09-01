<?php
/**
 * Migration runner tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\MigrationLock;
use WpRagAiChatbot\Database\MigrationRunner;
use WpRagAiChatbot\Database\MigrationStatus;
use WpRagAiChatbot\Database\SchemaVersionStore;

/**
 * Verifies ordered, lock-protected, failure-aware migration execution.
 */
final class MigrationRunnerTest extends TestCase {
	/**
	 * Pending migrations run in version order and persist each successful version.
	 */
	public function test_applies_pending_migrations_in_version_order(): void {
		self::assertTrue( class_exists( MigrationRunner::class ), 'MigrationRunner must exist before migration behavior can pass.' );

		$store = new FakeVersionStore( 0 );
		$lock  = new FakeMigrationLock( true );
		$log   = new MigrationLog();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array(
				new RecordingMigration( 2, $log ),
				new RecordingMigration( 1, $log ),
			)
		);

		self::assertSame( MigrationStatus::MIGRATED, $runner->run() );
		self::assertSame( array( 1, 2 ), $log->versions );
		self::assertSame( array( 1, 2 ), $store->writes );
		self::assertTrue( $lock->released );
	}

	/**
	 * Already-applied migrations are skipped.
	 */
	public function test_skips_versions_already_applied(): void {
		self::assertTrue( class_exists( MigrationRunner::class ), 'MigrationRunner must exist before migration behavior can pass.' );

		$store = new FakeVersionStore( 1 );
		$lock  = new FakeMigrationLock( true );
		$log   = new MigrationLog();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array(
				new RecordingMigration( 1, $log ),
				new RecordingMigration( 2, $log ),
			)
		);

		self::assertSame( MigrationStatus::MIGRATED, $runner->run() );
		self::assertSame( array( 2 ), $log->versions );
		self::assertSame( array( 2 ), $store->writes );
	}

	/**
	 * Current schemas avoid unnecessary lock acquisition.
	 */
	public function test_returns_up_to_date_without_taking_lock(): void {
		self::assertTrue( class_exists( MigrationRunner::class ), 'MigrationRunner must exist before migration behavior can pass.' );

		$store = new FakeVersionStore( 2 );
		$lock  = new FakeMigrationLock( true );
		$log   = new MigrationLog();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array(
				new RecordingMigration( 1, $log ),
				new RecordingMigration( 2, $log ),
			)
		);

		self::assertSame( MigrationStatus::UP_TO_DATE, $runner->run() );
		self::assertFalse( $lock->attempted );
		self::assertSame( array(), $log->versions );
	}

	/**
	 * Lock contention does not run schema changes.
	 */
	public function test_lock_contention_does_not_run_migrations(): void {
		self::assertTrue( class_exists( MigrationRunner::class ), 'MigrationRunner must exist before migration behavior can pass.' );

		$store = new FakeVersionStore( 0 );
		$lock  = new FakeMigrationLock( false );
		$log   = new MigrationLog();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array( new RecordingMigration( 1, $log ) )
		);

		self::assertSame( MigrationStatus::LOCKED, $runner->run() );
		self::assertSame( array(), $log->versions );
		self::assertSame( array(), $store->writes );
		self::assertFalse( $lock->released );
	}

	/**
	 * Failed migrations preserve the last successful version and release the lock.
	 */
	public function test_failure_does_not_advance_failed_version_and_releases_lock(): void {
		self::assertTrue( class_exists( MigrationRunner::class ), 'MigrationRunner must exist before migration behavior can pass.' );

		$store = new FakeVersionStore( 0 );
		$lock  = new FakeMigrationLock( true );
		$log   = new MigrationLog();

		$runner = new MigrationRunner(
			new NullConnection(),
			$store,
			$lock,
			array(
				new RecordingMigration( 1, $log ),
				new FailingMigration( 2 ),
			)
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'migration failed' );

		try {
			$runner->run();
		} finally {
			self::assertSame( array( 1 ), $store->writes );
			self::assertSame( array( 1 ), $log->versions );
			self::assertTrue( $lock->released );
		}
	}
}

/**
 * Mutable migration execution log shared by test migrations.
 */
final class MigrationLog {
	/** @var int[] */
	public array $versions = array();
}

/**
 * In-memory schema version store.
 */
final class FakeVersionStore implements SchemaVersionStore {
	/** @var int[] */
	public array $writes = array();

	/**
	 * Create a version store.
	 *
	 * @param int $version Initial version.
	 */
	public function __construct( private int $version ) {
	}

	/**
	 * Read current version.
	 */
	public function current(): int {
		return $this->version;
	}

	/**
	 * Save a version.
	 *
	 * @param int $version Applied version.
	 */
	public function save( int $version ): void {
		$this->version  = $version;
		$this->writes[] = $version;
	}
}

/**
 * In-memory migration lock.
 */
final class FakeMigrationLock implements MigrationLock {
	public bool $attempted = false;
	public bool $released  = false;

	/**
	 * Create a lock result fixture.
	 *
	 * @param bool $acquired Whether acquisition succeeds.
	 */
	public function __construct( private readonly bool $acquired ) {
	}

	/**
	 * Attempt lock acquisition.
	 */
	public function acquire(): bool {
		$this->attempted = true;
		return $this->acquired;
	}

	/**
	 * Release the lock.
	 */
	public function release(): void {
		$this->released = true;
	}
}

/**
 * Migration that records execution.
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

/**
 * Migration fixture that fails deterministically.
 */
final class FailingMigration implements Migration {
	/**
	 * Create a failing migration.
	 *
	 * @param int $migration_version Migration version.
	 */
	public function __construct( private readonly int $migration_version ) {
	}

	/**
	 * Get migration version.
	 */
	public function version(): int {
		return $this->migration_version;
	}

	/**
	 * Fail migration execution.
	 *
	 * @param Connection $connection Unused test connection.
	 * @throws RuntimeException Always.
	 */
	public function up( Connection $connection ): void {
		throw new RuntimeException( 'migration failed' );
	}
}

/**
 * No-op database connection for pure runner tests.
 */
final class NullConnection implements Connection {
	/** Get site prefix. */
	public function prefix(): string { return 'wp_'; }

	/** Get database name. */
	public function database_name(): string { return 'wordpress'; }

	/** Get charset/collation. */
	public function charset_collate(): string { return ''; }

	/** Prepare query. @param string $query Query. @param mixed ...$args Values. */
	public function prepare( string $query, mixed ...$args ): string { return $query; }

	/** Execute query. @param string $query Query. */
	public function query( string $query ): int|bool { return 0; }

	/** Read scalar. @param string $query Query. */
	public function get_var( string $query ): string|int|float|null { return null; }

	/** Read row. @param string $query Query. @return array<string, mixed>|null */
	public function get_row( string $query ): ?array { return null; }

	/** Read rows. @param string $query Query. @return array<int, array<string, mixed>> */
	public function get_results( string $query ): array { return array(); }

	/** Insert row. @param string $table Table. @param array<string, mixed> $data Data. @param string[] $format Formats. */
	public function insert( string $table, array $data, array $format = array() ): int|bool { return 1; }

	/** Update row. @param string $table Table. @param array<string, mixed> $data Data. @param array<string, mixed> $where Where. @param string[] $format Formats. @param string[] $where_format Where formats. */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int|bool { return 1; }

	/** Delete row. @param string $table Table. @param array<string, mixed> $where Where. @param string[] $where_format Formats. */
	public function delete( string $table, array $where, array $where_format = array() ): int|bool { return 1; }

	/** Read insert identifier. */
	public function insert_id(): int { return 1; }

	/** Apply dbDelta. @param string $sql SQL. @return array<int|string, mixed> */
	public function db_delta( string $sql ): array { return array(); }

	/** Check table. @param string $table Table. */
	public function table_exists( string $table ): bool { return true; }
}
