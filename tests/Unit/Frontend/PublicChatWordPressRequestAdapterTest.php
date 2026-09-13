<?php
/**
 * Public chat WordPress request adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Frontend\PublicChatClientScope;
use WpRagAiChatbot\Frontend\PublicChatWordPressRequestAdapter;

/**
 * Specifies the trusted WordPress-facing public request boundary.
 */
final class PublicChatWordPressRequestAdapterTest extends TestCase {
	/** The adapter accepts only the closed payload and derives identity from REMOTE_ADDR. */
	public function test_adapts_closed_payload_and_trusted_remote_address(): void {
		self::assertTrue( class_exists( PublicChatWordPressRequestAdapter::class ), 'PublicChatWordPressRequestAdapter is missing.' );

		$request = PublicChatWordPressRequestAdapter::request(
			array(
				'bot_id'          => '0123456789abcdef0123456789abcdef',
				'question'        => '  Where is my order?  ',
				'conversation_id' => ' conversation-1 ',
			)
		);

		self::assertSame( '0123456789abcdef0123456789abcdef', $request->bot_id );
		self::assertSame( 'Where is my order?', $request->question );
		self::assertSame( 'conversation-1', $request->conversation_id );

		$scope = PublicChatWordPressRequestAdapter::client_scope(
			array(
				'REMOTE_ADDR'          => '203.0.113.9',
				'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
			),
			'server-owned-secret'
		);

		self::assertSame(
			PublicChatClientScope::derive( '203.0.113.9', 'server-owned-secret' ),
			$scope
		);
	}

	/** Caller-controlled runtime override keys remain rejected by the closed request contract. */
	public function test_rejects_runtime_override_fields(): void {
		$this->expectException( InvalidArgumentException::class );

		PublicChatWordPressRequestAdapter::request(
			array(
				'bot_id'   => '0123456789abcdef0123456789abcdef',
				'question' => 'Hello',
				'provider' => 'openai',
			)
		);
	}
}
