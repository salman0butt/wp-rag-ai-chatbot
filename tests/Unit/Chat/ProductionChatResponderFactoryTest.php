<?php
/**
 * Shared production chat responder factory tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Chat\ChatOrchestrator;
use WpRagAiChatbot\Chat\ChatRequestPolicy;
use WpRagAiChatbot\Chat\ProductionChatResponderFactory;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Memory\ConversationHistory;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\RAG\DeterministicGroundingPolicy;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\QueryPreprocessor;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;

/**
 * Specifies one shared M11 graph composer for production consumers.
 */
final class ProductionChatResponderFactoryTest extends TestCase {
	/** Existing M10/M11 collaborators are composed into one ChatOrchestrator without executing them. */
	public function test_composes_existing_production_chat_orchestrator(): void {
		self::assertTrue( class_exists( ProductionChatResponderFactory::class ), 'ProductionChatResponderFactory is missing.' );

		$config    = new RetrievalConfig();
		$retriever = new HybridRetriever(
			$this->createMock( SemanticRetrievalChannel::class ),
			$this->createMock( LexicalRetrievalChannel::class ),
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			$this->createMock( CandidateAccessPolicy::class ),
			$config
		);
		$factory   = new ProductionChatResponderFactory(
			new ChatRequestPolicy( new QueryPreprocessor( $config ) ),
			new MemoryAssembler( $this->createMock( ConversationHistory::class ) ),
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			new CitationValidator()
		);

		$responder = $factory->create( $retriever, $this->createMock( GenerationProvider::class ) );

		self::assertInstanceOf( ChatOrchestrator::class, $responder );
	}
}
