<?php
/**
 * Public chat WordPress REST bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\PublicChatRestBootstrap;

/**
 * Specifies the narrow public REST route contract.
 */
final class PublicChatRestBootstrapTest extends TestCase {
	/** The public transport uses one versioned POST route and no authentication gate. */
	public function test_exposes_one_public_post_route_contract(): void {
		self::assertTrue( class_exists( PublicChatRestBootstrap::class ), 'PublicChatRestBootstrap is missing.' );
		self::assertSame( 'wp-rag-ai-chatbot/v1', PublicChatRestBootstrap::REST_NAMESPACE );
		self::assertSame( '/chat', PublicChatRestBootstrap::REST_ROUTE );

		$definition = PublicChatRestBootstrap::route_definition();

		self::assertSame( 'POST', $definition['methods'] ?? null );
		self::assertSame( array( PublicChatRestBootstrap::class, 'run_chat' ), $definition['callback'] ?? null );
		self::assertSame( array( PublicChatRestBootstrap::class, 'allow_public' ), $definition['permission_callback'] ?? null );
		self::assertTrue( PublicChatRestBootstrap::allow_public() );
		self::assertArrayNotHasKey( 'args', $definition );
	}
}
