<?php
/**
 * M13 typed Playground execution contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Verifies Playground production execution is a typed boundary rather than an arbitrary array callback.
 */
final class PlaygroundExecutionContractTest extends TestCase {
	/** The executor and result contract must be explicit and typed. */
	public function test_playground_execution_contract_is_typed(): void {
		$executor = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundExecutor';
		$result   = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundExecutionResult';

		self::assertTrue(
			interface_exists( $executor ) && class_exists( $result ),
			'Playground executor and result contracts must exist.'
		);

		$method = new ReflectionMethod( $executor, 'execute' );
		self::assertSame( 'string', (string) $method->getParameters()[0]->getType() );
		self::assertSame( $result, (string) $method->getReturnType() );

		$result_reflection = new ReflectionClass( $result );
		self::assertTrue( $result_reflection->isReadOnly() );
		self::assertTrue( $result_reflection->isFinal() );

		$constructor = $result_reflection->getConstructor();
		self::assertNotNull( $constructor );
		$parameters = $constructor->getParameters();
		self::assertCount( 4, $parameters );
		self::assertSame( 'WpRagAiChatbot\\Chat\\ChatResult', (string) $parameters[0]->getType() );
		self::assertSame( 'WpRagAiChatbot\\Debug\\DebugTrace', (string) $parameters[1]->getType() );
		self::assertSame( 'string', (string) $parameters[2]->getType() );
		self::assertSame( 'int', (string) $parameters[3]->getType() );
	}
}
