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
use WpRagAiChatbot\Database\MigrationLock;
use WpRagAiChatbot\Database\MigrationRunner;
use WpRagAiChatbot\Database\MigrationStatus;
use WpRagAiChatbot\Tests\Support\Database\FailingMigration;
use WpRagAiChatbot\Tests\Support\Database\FakeMigrationLock;
use WpRagAiChatbot\Tests\Support\Database\FakeVersionStore;
use WpRagAiChatbot\Tests\Support\Database\MigrationLog;
use WpRagAiChatbot\Tests\Support\Database\NullConnection;
use WpRagAiChatbot\Tests\Support\Database\RecordingMigration;

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
	 * A version changed by another process while waiting to acquire the lock is refreshed before DDL runs.
	 */
	public function test_refreshes_schema_version_after_lock_acquisition(): void {
		$store = new FakeVersionStore( 0 );
		$log   = new MigrationLog();
		$lock  = new class( $store ) implements MigrationLock {
			/**
			 * Create the race-condition fixture.
			 *
			 * @param FakeVersionStore $store Shared version store.
			 */
			public function __construct( private readonly FakeVersionStore $store ) {
			}

			/**
			 * Simulate another process completing migrations immediately before this process owns the lock.
			 */
			public function acquire(): bool {
				$this->store->advanceExternally( 2 );
				return true;
			}

			/**
			 * No-op release fixture.
			 */
			public function release(): void {
			}
		};

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
		self::assertSame( array(), $log->versions );
		self::assertSame( array(), $store->writes );
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
