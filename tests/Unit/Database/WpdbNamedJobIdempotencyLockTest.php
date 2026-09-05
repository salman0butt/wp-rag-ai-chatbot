<?php
/**
 * M09 named idempotency-lock tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\JobIdempotencyLock;
use WpRagAiChatbot\Database\WpdbNamedJobIdempotencyLock;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Defines the short site-scoped enqueue-deduplication lock before implementation.
 */
final class WpdbNamedJobIdempotencyLockTest extends TestCase {
	/**
	 * The production lock implements the narrow acquire/release contract.
	 */
	public function test_named_job_lock_contract_exists(): void {
		self::assertTrue( interface_exists( JobIdempotencyLock::class ), 'Task 2 requires JobIdempotencyLock.' );
		self::assertTrue( class_exists( WpdbNamedJobIdempotencyLock::class ), 'Task 2 requires WpdbNamedJobIdempotencyLock.' );
		self::assertContains( JobIdempotencyLock::class, class_implements( WpdbNamedJobIdempotencyLock::class ) );
	}

	/**
	 * Lock identities are deterministic, bounded, site-scoped and key-scoped.
	 */
	public function test_lock_identity_is_deterministic_and_scoped(): void {
		self::assertTrue( class_exists( WpdbNamedJobIdempotencyLock::class ), 'Task 2 requires WpdbNamedJobIdempotencyLock.' );

		$first  = new RecordingConnection( 'wordpress_db', 'wp_', 1 );
		$same   = new RecordingConnection( 'wordpress_db', 'wp_', 1 );
		$site   = new RecordingConnection( 'wordpress_db', 'wp_2_', 1 );
		$jobkey = new RecordingConnection( 'wordpress_db', 'wp_', 1 );

		self::assertTrue( ( new WpdbNamedJobIdempotencyLock( $first, 'index.document', 'document:42' ) )->acquire() );
		self::assertTrue( ( new WpdbNamedJobIdempotencyLock( $same, 'index.document', 'document:42' ) )->acquire() );
		self::assertTrue( ( new WpdbNamedJobIdempotencyLock( $site, 'index.document', 'document:42' ) )->acquire() );
		self::assertTrue( ( new WpdbNamedJobIdempotencyLock( $jobkey, 'index.document', 'document:43' ) )->acquire() );

		$first_name = $first->prepared_calls[0]['args'][0];
		self::assertIsString( $first_name );
		self::assertSame( $first_name, $same->prepared_calls[0]['args'][0] );
		self::assertNotSame( $first_name, $site->prepared_calls[0]['args'][0] );
		self::assertNotSame( $first_name, $jobkey->prepared_calls[0]['args'][0] );
		self::assertStringStartsWith( 'wp_rag_ai_job_', $first_name );
		self::assertLessThanOrEqual( 64, strlen( $first_name ) );
		self::assertSame( 'SELECT GET_LOCK(%s, 0)', $first->prepared_calls[0]['query'] );
	}

	/**
	 * Release always targets the exact lock identity acquired by this instance.
	 */
	public function test_release_uses_same_lock_identity(): void {
		self::assertTrue( class_exists( WpdbNamedJobIdempotencyLock::class ), 'Task 2 requires WpdbNamedJobIdempotencyLock.' );

		$connection = new RecordingConnection( 'wordpress_db', 'wp_', 1 );
		$lock       = new WpdbNamedJobIdempotencyLock( $connection, 'index.document', 'document:42' );

		$lock->acquire();
		$lock->release();

		self::assertSame( 'SELECT RELEASE_LOCK(%s)', $connection->prepared_calls[1]['query'] );
		self::assertSame( $connection->prepared_calls[0]['args'][0], $connection->prepared_calls[1]['args'][0] );
	}
}
