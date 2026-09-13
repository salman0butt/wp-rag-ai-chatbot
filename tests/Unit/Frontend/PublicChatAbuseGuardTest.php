<?php
/**
 * Public chat abuse-control boundary tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\PublicChatAbuseGuard;
use WpRagAiChatbot\Frontend\PublicChatRateLimitStore;

/**
 * Specifies deterministic server-side abuse controls before expensive chat work.
 */
final class PublicChatAbuseGuardTest extends TestCase {
	/** The abuse-control service and persistence seam must exist. */
	public function test_abuse_control_contracts_exist(): void {
		self::assertTrue( interface_exists( PublicChatRateLimitStore::class ), 'PublicChatRateLimitStore is missing.' );
		self::assertTrue( class_exists( PublicChatAbuseGuard::class ), 'PublicChatAbuseGuard is missing.' );
	}

	/** A valid opaque client scope consumes one bounded per-bot bucket. */
	public function test_allows_when_rate_limit_store_accepts_bucket(): void {
		self::assertTrue( interface_exists( PublicChatRateLimitStore::class ), 'PublicChatRateLimitStore is missing.' );
		self::assertTrue( class_exists( PublicChatAbuseGuard::class ), 'PublicChatAbuseGuard is missing.' );

		$store        = $this->createMock( PublicChatRateLimitStore::class );
		$bot_id       = '0123456789abcdef0123456789abcdef';
		$client_scope = str_repeat( 'a', 64 );

		$store->expects( self::once() )
			->method( 'consume' )
			->with(
				self::callback(
					static function ( string $bucket ): bool {
						return str_starts_with( $bucket, 'wp_rag_public_chat_' )
							&& ! str_contains( $bucket, '0123456789abcdef0123456789abcdef' )
							&& ! str_contains( $bucket, str_repeat( 'a', 64 ) );
					}
				),
				20,
				60
			)
			->willReturn( true );

		self::assertTrue( ( new PublicChatAbuseGuard( $store ) )->allow( $bot_id, $client_scope ) );
	}

	/** Store denial stops the request before runtime work. */
	public function test_denies_when_rate_limit_store_rejects_bucket(): void {
		self::assertTrue( interface_exists( PublicChatRateLimitStore::class ), 'PublicChatRateLimitStore is missing.' );
		self::assertTrue( class_exists( PublicChatAbuseGuard::class ), 'PublicChatAbuseGuard is missing.' );

		$store = $this->createMock( PublicChatRateLimitStore::class );
		$store->expects( self::once() )->method( 'consume' )->willReturn( false );

		self::assertFalse(
			( new PublicChatAbuseGuard( $store ) )->allow(
				'0123456789abcdef0123456789abcdef',
				str_repeat( 'b', 64 )
			)
		);
	}

	/** Raw or malformed client identity is rejected instead of entering rate-limit storage. */
	public function test_rejects_non_opaque_client_scope_without_consuming_store(): void {
		self::assertTrue( interface_exists( PublicChatRateLimitStore::class ), 'PublicChatRateLimitStore is missing.' );
		self::assertTrue( class_exists( PublicChatAbuseGuard::class ), 'PublicChatAbuseGuard is missing.' );

		$store = $this->createMock( PublicChatRateLimitStore::class );
		$store->expects( self::never() )->method( 'consume' );

		self::assertFalse(
			( new PublicChatAbuseGuard( $store ) )->allow(
				'0123456789abcdef0123456789abcdef',
				'203.0.113.42'
			)
		);
	}
}
