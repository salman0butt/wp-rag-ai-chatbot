<?php
/**
 * M13 production Playground runtime-bootstrap contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WpRagAiChatbot\Admin\Rest\PlaygroundRequestHandler;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;

/**
 * Specifies one WordPress production composition authority for the Playground request handler.
 */
final class PlaygroundRuntimeBootstrapTest extends TestCase {
	/** The runtime bootstrap must accept only existing database authorities and return the production handler. */
	public function test_runtime_bootstrap_contract_is_thin_and_production_owned(): void {
		$bootstrap_class = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundRuntimeBootstrap';
		self::assertTrue( class_exists( $bootstrap_class ), 'PlaygroundRuntimeBootstrap is missing.' );

		$handler = new ReflectionMethod( $bootstrap_class, 'handler' );
		self::assertTrue( $handler->isStatic() );
		self::assertSame(
			array( Connection::class, TableNames::class ),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$handler->getParameters()
			)
		);
		self::assertSame( PlaygroundRequestHandler::class, (string) $handler->getReturnType() );
	}
}
