<?php
/**
 * M13 production Playground executor composition contract test.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Admin\Rest\PlaygroundExecutor;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatOrchestrator;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\RAG\GroundingMode;

/**
 * Specifies the thin request-local production executor boundary.
 */
final class ProductionPlaygroundExecutorContractTest extends TestCase {
	/** The production executor must reuse one M11 orchestrator and exact retrieval capture. */
	public function test_production_executor_has_the_expected_typed_dependencies(): void {
		$class_name = 'WpRagAiChatbot\\Admin\\Rest\\ProductionPlaygroundExecutor';
		self::assertTrue( class_exists( $class_name ), 'ProductionPlaygroundExecutor must exist.' );

		$class = new ReflectionClass( $class_name );
		self::assertTrue( $class->isFinal() );
		self::assertTrue( $class->implementsInterface( PlaygroundExecutor::class ) );

		$constructor = $class->getConstructor();
		self::assertNotNull( $constructor );
		$parameters = $constructor->getParameters();
		self::assertCount( 6, $parameters );
		self::assertSame( ChatOrchestrator::class, (string) $parameters[0]->getType() );
		self::assertSame( ChatAccessContext::class, (string) $parameters[1]->getType() );
		self::assertSame(
			'WpRagAiChatbot\\Admin\\Rest\\PlaygroundRetrievalCapture',
			(string) $parameters[2]->getType()
		);
		self::assertSame( DebugTraceProjector::class, (string) $parameters[3]->getType() );
		self::assertSame( 'string', (string) $parameters[4]->getType() );
		self::assertSame( GroundingMode::class, (string) $parameters[5]->getType() );
	}
}
