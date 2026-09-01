<?php
/**
 * Named migration lock tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\WpdbNamedMigrationLock;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Verifies deterministic, non-blocking database advisory locking.
 */
final class WpdbNamedMigrationLockTest extends TestCase {
	/**
	 * Lock name is deterministic and bounded for one site identity.
	 */
	public function test_lock_name_is_deterministic_and_bounded(): void {
		self::assertTrue( class_exists( WpdbNamedMigrationLock::class ), 'WpdbNamedMigrationLock must exist before lock behavior can pass.' );

		$first_connection  = new RecordingConnection( 'wordpress_db', 'wp_', 1 );
		$second_connection = new RecordingConnection( 'wordpress_db', 'wp_', 1 );

		self::assertTrue( ( new WpdbNamedMigrationLock( $first_connection ) )->acquire() );
		self::assertTrue( ( new WpdbNamedMigrationLock( $second_connection ) )->acquire() );

		$first_name  = $first_connection->prepared_calls[0]['args'][0];
		$second_name = $second_connection->prepared_calls[0]['args'][0];

		self::assertIsString( $first_name );
		self::assertSame( $first_name, $second_name );
		self::assertStringStartsWith( 'wp_rag_ai_migrate_', $first_name );
		self::assertLessThanOrEqual( 64, strlen( $first_name ) );
	}

	/**
	 * Different site prefixes receive different lock identities.
	 */
	public function test_different_site_prefixes_use_different_lock_names(): void {
		self::assertTrue( class_exists( WpdbNamedMigrationLock::class ), 'WpdbNamedMigrationLock must exist before lock behavior can pass.' );

		$first_connection  = new RecordingConnection( 'wordpress_db', 'wp_', 1 );
		$second_connection = new RecordingConnection( 'wordpress_db', 'wp_2_', 1 );

		( new WpdbNamedMigrationLock( $first_connection ) )->acquire();
		( new WpdbNamedMigrationLock( $second_connection ) )->acquire();

		self::assertNotSame(
			$first_connection->prepared_calls[0]['args'][0],
			$second_connection->prepared_calls[0]['args'][0]
		);
	}

	/**
	 * Only MySQL's successful GET_LOCK scalar is accepted.
	 *
	 * @param string|int|float|null $database_result Scalar returned by the database.
	 * @param bool                  $expected Expected acquisition result.
	 */
	#[DataProvider( 'lock_result_provider' )]
	public function test_acquire_accepts_only_success_scalar( string|int|float|null $database_result, bool $expected ): void {
		self::assertTrue( class_exists( WpdbNamedMigrationLock::class ), 'WpdbNamedMigrationLock must exist before lock behavior can pass.' );

		$connection = new RecordingConnection( 'wordpress_db', 'wp_', $database_result );
		$lock       = new WpdbNamedMigrationLock( $connection );

		self::assertSame( $expected, $lock->acquire() );
		self::assertSame( 'SELECT GET_LOCK(%s, 0)', $connection->prepared_calls[0]['query'] );
	}

	/**
	 * Provide advisory-lock scalar outcomes.
	 *
	 * @return array<string, array{0: string|int|float|null, 1: bool}>
	 */
	public static function lock_result_provider(): array {
		return array(
			'integer success' => array( 1, true ),
			'string success'  => array( '1', true ),
			'zero'            => array( 0, false ),
			'null'            => array( null, false ),
		);
	}

	/**
	 * Release uses the same deterministic lock identity.
	 */
	public function test_release_uses_release_lock_query(): void {
		self::assertTrue( class_exists( WpdbNamedMigrationLock::class ), 'WpdbNamedMigrationLock must exist before lock behavior can pass.' );

		$connection = new RecordingConnection( 'wordpress_db', 'wp_', 1 );
		$lock       = new WpdbNamedMigrationLock( $connection );

		$lock->acquire();
		$lock->release();

		self::assertSame( 'SELECT RELEASE_LOCK(%s)', $connection->prepared_calls[1]['query'] );
		self::assertSame( $connection->prepared_calls[0]['args'][0], $connection->prepared_calls[1]['args'][0] );
	}
}
