<?php
/**
 * Public chat WordPress REST bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace {
	if ( ! class_exists( 'WP_REST_Request' ) ) {
		/** Minimal request double for callback contract tests. */
		class WP_REST_Request {
			/**
			 * @param array<string,mixed> $payload JSON payload.
			 */
			public function __construct( private readonly array $payload ) {
			}

			/** @return array<string,mixed> */
			public function get_json_params(): array {
				return $this->payload;
			}
		}
	}
}

namespace WpRagAiChatbot\Tests\Unit\Frontend {

	use PHPUnit\Framework\TestCase;
	use WP_REST_Request;
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

		/** Malformed caller-controlled runtime fields fail closed before runtime composition. */
		public function test_run_chat_rejects_unknown_runtime_override_fields(): void {
			$result = PublicChatRestBootstrap::run_chat(
				new WP_REST_Request(
					array(
						'bot_id'   => '0123456789abcdef0123456789abcdef',
						'question' => 'Hello',
						'provider' => 'openai',
					)
				)
			);

			self::assertSame(
				array(
					'error' => array(
						'code' => 'invalid_request',
					),
				),
				$result
			);
		}
	}
}
