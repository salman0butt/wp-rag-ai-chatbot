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
use WpRagAiChatbot\Chat\ChatRequestPolicy;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Debug\DebugTraceProjector;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\RAG\GroundingPolicy;
use WpRagAiChatbot\RAG\PromptBuilder;
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
	 * Composition must accept only existing production seams and return the typed production executor.
	 */
	public function test_resolver_exposes_existing_production_graph_dependencies(): void {
		$class       = new ReflectionClass( 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundChatGraphResolver' );
		$constructor = $class->getConstructor();
		self::assertNotNull( $constructor );

		self::assertSame(
			array(
				ChatRequestPolicy::class,
				MemoryAssembler::class,
				GroundingPolicy::class,
				PromptBuilder::class,
				CitationValidator::class,
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
