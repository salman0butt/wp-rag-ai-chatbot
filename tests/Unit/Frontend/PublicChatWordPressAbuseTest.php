<?php
/**
 * WordPress public-chat abuse adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Frontend\PublicChatClientScope;
use WpRagAiChatbot\Frontend\WpdbPublicChatRateLimitStore;

/**
 * Specifies server-derived client identity and atomic WordPress rate persistence.
 */
final class PublicChatWordPressAbuseTest extends TestCase {
	/** Client identity is deterministic, opaque, salted, and never raw request metadata. */
	public function test_derives_opaque_client_scope_from_server_remote_address(): void {
		self::assertTrue( class_exists( PublicChatClientScope::class ), 'PublicChatClientScope is missing.' );

		$scope = PublicChatClientScope::derive( '203.0.113.10', 'test-only-secret' );

		self::assertMatchesRegularExpression( '/\A[a-f0-9]{64}\z/D', $scope );
		self::assertSame( $scope, PublicChatClientScope::derive( '203.0.113.10', 'test-only-secret' ) );
		self::assertNotSame( $scope, PublicChatClientScope::derive( '203.0.113.11', 'test-only-secret' ) );
		self::assertStringNotContainsString( '203.0.113.10', $scope );
	}

	/** Missing or malformed remote addresses share an opaque fallback scope; a secret is mandatory. */
	public function test_client_scope_fails_closed_without_valid_identity_or_secret(): void {
		self::assertTrue( class_exists( PublicChatClientScope::class ), 'PublicChatClientScope is missing.' );

		$fallback = PublicChatClientScope::derive( null, 'test-only-secret' );
		self::assertMatchesRegularExpression( '/\A[a-f0-9]{64}\z/D', $fallback );
		self::assertSame( $fallback, PublicChatClientScope::derive( 'not-an-ip', 'test-only-secret' ) );

		$this->expectException( InvalidArgumentException::class );
		PublicChatClientScope::derive( '203.0.113.10', '' );
	}

	/** One SQL statement atomically consumes a bucket; unchanged rows mean the limit denied the request. */
	public function test_wpdb_store_uses_one_atomic_options_statement(): void {
		self::assertTrue( class_exists( WpdbPublicChatRateLimitStore::class ), 'WpdbPublicChatRateLimitStore is missing.' );

		$connection = $this->createMock( Connection::class );
		$connection->method( 'prefix' )->willReturn( 'wp_' );
		$connection->expects( self::once() )
			->method( 'prepare' )
			->with(
				self::callback(
					static fn ( string $sql ): bool =>
						str_contains( $sql, 'INSERT INTO wp_options' )
						&& str_contains( $sql, 'ON DUPLICATE KEY UPDATE' )
						&& str_contains( $sql, 'option_value' )
				),
				'wp_rag_public_chat_opaque',
				self::isType( 'int' ),
				self::isType( 'int' ),
				20
			)
			->willReturn( 'prepared-atomic-consume' );
		$connection->expects( self::once() )
			->method( 'query' )
			->with( 'prepared-atomic-consume' )
			->willReturn( 1 );

		$store = new WpdbPublicChatRateLimitStore( $connection );
		self::assertTrue( $store->consume( 'wp_rag_public_chat_opaque', 20, 60 ) );
	}

	/** Invalid persistence inputs are denied before SQL execution. */
	public function test_wpdb_store_rejects_invalid_budget_inputs(): void {
		self::assertTrue( class_exists( WpdbPublicChatRateLimitStore::class ), 'WpdbPublicChatRateLimitStore is missing.' );

		$connection = $this->createMock( Connection::class );
		$connection->expects( self::never() )->method( 'prepare' );
		$connection->expects( self::never() )->method( 'query' );

		$store = new WpdbPublicChatRateLimitStore( $connection );
		self::assertFalse( $store->consume( '', 20, 60 ) );
		self::assertFalse( $store->consume( 'bucket', 0, 60 ) );
		self::assertFalse( $store->consume( 'bucket', 20, 0 ) );
	}
}
