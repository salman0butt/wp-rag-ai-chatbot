<?php
/**
 * Strict no-answer persistence regression coverage for M11 Task 7.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatOrchestrator;
use WpRagAiChatbot\Chat\ChatRequest;
use WpRagAiChatbot\Chat\ChatRequestPolicy;
use WpRagAiChatbot\Citations\CitationValidator;
use WpRagAiChatbot\Conversations\ConversationMessage;
use WpRagAiChatbot\Conversations\MessageRepository;
use WpRagAiChatbot\Memory\ConversationHistory;
use WpRagAiChatbot\Memory\MemoryAssembler;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\RAG\DeterministicGroundingPolicy;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetrievalChannel;
use WpRagAiChatbot\Retrieval\QueryPreprocessor;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalQuery;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalChannel;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;

/**
 * Proves deterministic strict no-answer does not write assistant persistence.
 */
final class ChatOrchestratorNoAnswerPersistenceTest extends TestCase {
	/**
	 * Strict insufficient evidence must skip both generation and persistence.
	 */
	public function test_strict_no_answer_skips_configured_persistence_repository(): void {
		$config      = new RetrievalConfig();
		$provider    = $this->provider();
		$persistence = $this->persistence();
		$orchestrator = new ChatOrchestrator(
			new ChatRequestPolicy( new QueryPreprocessor( $config ) ),
			new MemoryAssembler( $this->history() ),
			$this->retriever( $config ),
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			$provider,
			new CitationValidator(),
			$persistence
		);

		$result = $orchestrator->respond(
			new ChatRequest( 'What is supported?', 'model-test', GroundingMode::STRICT, 'conversation-1' ),
			new ChatAccessContext( 'owner-secret', $this->semantic_context(), $this->lexical_filter() )
		);

		self::assertTrue( $result->no_answer );
		self::assertSame( 0, $provider->generate_calls ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double call count.
		self::assertSame( 0, $persistence->append_calls ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double call count.
	}

	/**
	 * Empty owner-scoped history.
	 */
	private function history(): ConversationHistory {
		return new class() implements ConversationHistory {
			/** {@inheritDoc} */
			public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array {
				return array();
			}

			/** {@inheritDoc} */
			public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array {
				return null;
			}
		};
	}

	/**
	 * Empty deterministic hybrid retrieval.
	 */
	private function retriever( RetrievalConfig $config ): HybridRetriever {
		$semantic = new class() implements SemanticRetrievalChannel {
			/** {@inheritDoc} */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				return array();
			}
		};
		$lexical  = new class() implements LexicalRetrievalChannel {
			/** {@inheritDoc} */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				return array();
			}
		};
		$access   = new class() implements CandidateAccessPolicy {
			/** {@inheritDoc} */
			public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				return true;
			}
		};

		return new HybridRetriever(
			$semantic,
			$lexical,
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			$access,
			$config
		);
	}

	/**
	 * Provider that records unexpected generation.
	 */
	private function provider(): GenerationProvider {
		return new class() implements GenerationProvider {
			/**
			 * Number of generation calls.
			 *
			 * @var int
			 */
			public int $generate_calls = 0;

			/** {@inheritDoc} */
			public function provider_id(): string {
				return 'no-answer-persistence-fake';
			}

			/** {@inheritDoc} */
			public function available(): bool {
				return true;
			}

			/** {@inheritDoc} */
			public function generate( GenerationRequest $request ): GenerationResult {
				++$this->generate_calls;
				throw new \LogicException( 'Generation must not run for strict no-answer.' );
			}
		};
	}

	/**
	 * Persistence fake that records unexpected writes.
	 */
	private function persistence(): MessageRepository {
		return new class() implements MessageRepository {
			/**
			 * Number of append calls.
			 *
			 * @var int
			 */
			public int $append_calls = 0;

			/** {@inheritDoc} */
			public function append_for_owner( string $conversation_id, string $owner_scope, ConversationMessage $message ): void {
				++$this->append_calls;
			}
		};
	}

	/**
	 * Trusted semantic retrieval scope.
	 */
	private function semantic_context(): SemanticRetrievalContext {
		return new SemanticRetrievalContext(
			new RetrievalFilter( 'public', 'en', array( 8 ) ),
			static fn (): null => null
		);
	}

	/**
	 * Trusted lexical retrieval scope.
	 */
	private function lexical_filter(): LexicalFilter {
		return new LexicalFilter( 'collection-1', null, 8, 'en', 'public' );
	}
}
