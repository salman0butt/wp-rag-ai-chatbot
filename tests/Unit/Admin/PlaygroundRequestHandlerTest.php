<?php
/**
 * M13 Playground request handler contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use WpRagAiChatbot\Admin\Rest\PlaygroundConfigurationResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundExecutorResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundRequest;

/**
 * Specifies the thin request-to-production-executor binding seam.
 */
final class PlaygroundRequestHandlerTest extends TestCase {
	/** The handler may depend only on persisted configuration and production executor authorities. */
	public function test_handler_contract_uses_existing_production_authorities(): void {
		$handler_class = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundRequestHandler';
		self::assertTrue( class_exists( $handler_class ), 'PlaygroundRequestHandler is missing.' );

		$constructor = ( new ReflectionClass( $handler_class ) )->getConstructor();
		self::assertNotNull( $constructor );
		self::assertSame(
			array(
				PlaygroundConfigurationResolver::class,
				PlaygroundExecutorResolver::class,
			),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$constructor->getParameters()
			)
		);

		$handle = new ReflectionMethod( $handler_class, 'handle' );
		self::assertSame( PlaygroundRequest::class, (string) $handle->getParameters()[0]->getType() );
		self::assertSame( 'array', (string) $handle->getReturnType() );
	}
}
