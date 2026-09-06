<?php
/**
 * Strict no-answer persistence coverage for M11 Task 7.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use LogicException;
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
 * Proves deterministic strict no-answer cannot write assistant persistence.
 */
final class ChatOrchestratorNoAnswerPersistenceTest extends TestCase {
	/**
	 * Strict insufficient evidence skips generation and a configured persistence repository.
	 */
	public function test_strict_no_answer_skips_configured_persistence_repository(): void {
		$config       = new RetrievalConfig();
		$provider     = $this->provider();
		$persistence  = $this->persistence();
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
		self::assertSame( 0, $provider->generate_calls );
		self::assertSame( 0, $persistence->append_calls );
	}

	/**
	 * Create empty owner-scoped history.
	 */
	private function history(): ConversationHistory {
		return new class() implements ConversationHistory {
			/**
			 * Return no recent messages.
			 *
			 * @param string $conversation_id Conversation identifier.
			 * @param string $owner_scope Trusted owner scope.
			 * @param int    $limit Maximum requested messages.
			 * @return list<ConversationMessage>
			 */
			public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array {
				return array();
			}

			/**
			 * Return no conversation summary.
			 *
			 * @param string $conversation_id Conversation identifier.
			 * @param string $owner_scope Trusted owner scope.
			 * @return array{summary:string,version:int}|null
			 */
			public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array {
				return null;
			}
		};
	}

	/**
	 * Create an empty deterministic hybrid retriever.
	 *
	 * @param RetrievalConfig $config Existing retrieval hard bounds.
	 */
	private function retriever( RetrievalConfig $config ): HybridRetriever {
		$semantic = new class() implements SemanticRetrievalChannel {
			/**
			 * Return no semantic candidates.
			 *
			 * @param RetrievalQuery           $query Normalized retrieval query.
			 * @param SemanticRetrievalContext $context Trusted semantic scope.
			 * @return list<\WpRagAiChatbot\Retrieval\Fusion\RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				return array();
			}
		};
		$lexical  = new class() implements LexicalRetrievalChannel {
			/**
			 * Return no lexical candidates.
			 *
			 * @param RetrievalQuery $query Normalized retrieval query.
			 * @param LexicalFilter  $filter Trusted lexical scope.
			 * @return list<\WpRagAiChatbot\Retrieval\Fusion\RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				return array();
			}
		};
		$access   = new class() implements CandidateAccessPolicy {
			/**
			 * Permit a candidate if one were ever supplied to this empty fixture.
			 *
			 * @param RetrievalCandidate $candidate Retrieval candidate.
			 * @param RetrievalFilter    $filter Trusted retrieval filter.
			 */
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
	 * Create a provider that records any unexpected generation call.
	 */
	private function provider(): GenerationProvider {
		return new class() implements GenerationProvider {
			/** Number of generation calls. */
			public int $generate_calls = 0;

			/** {@inheritDoc} */
			public function provider_id(): string {
				return 'strict-no-answer-fake';
			}

			/** {@inheritDoc} */
			public function available(): bool {
				return true;
			}

			/**
			 * Reject any unexpected generation attempt.
			 *
			 * @param GenerationRequest $request Generation request that must never be used.
			 * @throws LogicException Always, because strict no-answer must stop before generation.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				++$this->generate_calls;
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test sentinel is never rendered.
				throw new LogicException( 'Strict no-answer must not generate.' );
			}
		};
	}

	/**
	 * Create persistence that records any unexpected assistant write.
	 */
	private function persistence(): MessageRepository {
		return new class() implements MessageRepository {
			/** Number of append calls. */
			public int $append_calls = 0;

			/**
			 * Record an unexpected owner-scoped assistant append.
			 *
			 * @param string              $conversation_id Conversation identifier.
			 * @param string              $owner_scope Trusted owner scope.
			 * @param ConversationMessage $message Assistant message.
			 */
			public function append_for_owner( string $conversation_id, string $owner_scope, ConversationMessage $message ): void {
				++$this->append_calls;
			}
		};
	}

	/**
	 * Create trusted semantic retrieval scope.
	 */
	private function semantic_context(): SemanticRetrievalContext {
		return new SemanticRetrievalContext(
			new RetrievalFilter( 'public', 'en', array( 8 ) ),
			static fn (): null => null
		);
	}

	/**
	 * Create trusted lexical retrieval scope.
	 */
	private function lexical_filter(): LexicalFilter {
		return new LexicalFilter( 'collection-1', null, 8, 'en', 'public' );
	}
}
