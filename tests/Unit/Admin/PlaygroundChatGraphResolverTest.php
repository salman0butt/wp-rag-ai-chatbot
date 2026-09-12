<?php
/**
 * Playground production chat-graph composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ProductionChatResponderFactory;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Retrieval\HybridRetriever;

/**
 * Specifies the request-local production Playground chat-graph composition boundary.
 */
final class PlaygroundChatGraphResolverTest extends TestCase {
	/**
	 * The Playground requires one dedicated composition boundary around the existing M10/M11 graph.
	 */
	public function test_playground_chat_graph_resolver_contract_exists(): void {
		self::assertTrue(
			class_exists( 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundChatGraphResolver' ),
			'PlaygroundChatGraphResolver contract is missing.'
		);
	}

	/**
	 * Composition must reuse the shared production M11 responder factory rather than rebuilding the graph.
	 */
	public function test_resolver_reuses_shared_production_responder_factory(): void {
		$class       = new ReflectionClass( 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundChatGraphResolver' );
		$constructor = $class->getConstructor();
		self::assertNotNull( $constructor );

		self::assertSame(
			array(
				ProductionChatResponderFactory::class,
				DebugTraceProjector::class,
			),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$constructor->getParameters()
			)
		);

		$resolve = $class->getMethod( 'resolve' );
		self::assertSame(
			array(
				HybridRetriever::class,
				GenerationProvider::class,
				ChatAccessContext::class,
				'WpRagAiChatbot\\Admin\\Rest\\PlaygroundRetrievalCapture',
				'string',
			),
			array_map(
				static fn ( \ReflectionParameter $parameter ): string => (string) $parameter->getType(),
				$resolve->getParameters()
			)
		);
		self::assertSame(
			'WpRagAiChatbot\\Admin\\Rest\\ProductionPlaygroundExecutor',
			(string) $resolve->getReturnType()
		);
	}
}
