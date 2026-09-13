<?php
/**
 * Public chat WordPress REST bootstrap tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

require_once dirname( __DIR__, 2 ) . '/Fixtures/WP_REST_Request.php';

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_REST_Request;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\PublicChatProductionExecutorResolver;
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

	/** The REST bootstrap exposes only the canonical production executor-composition seam. */
	public function test_executor_resolver_contract_uses_existing_runtime_authorities(): void {
		$class  = new ReflectionClass( PublicChatRestBootstrap::class );
		$method = $class->getMethod( 'executor_resolver' );

		self::assertTrue( $method->isStatic() );
		self::assertSame(
			array( Connection::class, TableNames::class ),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$method->getParameters()
			)
		);
		self::assertSame( PublicChatProductionExecutorResolver::class, (string) $method->getReturnType() );
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
