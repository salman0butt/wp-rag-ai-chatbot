<?php
/**
 * Scoped persistence regression tests for M11 Task 7.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatException;
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
use WpRagAiChatbot\Providers\GenerationStatus;
use WpRagAiChatbot\Providers\Usage;
use WpRagAiChatbot\RAG\DeterministicGroundingPolicy;
use WpRagAiChatbot\RAG\GroundingMode;
use WpRagAiChatbot\RAG\PromptBuilder;
use WpRagAiChatbot\Retrieval\Access\CandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
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
 * Proves validated assistant output crosses an owner-scoped persistence boundary exactly once.
 */
final class ChatOrchestratorPersistenceTest extends TestCase {
	/**
	 * Successful validated output persists after generation with trusted owner scope.
	 */
	public function test_validated_answer_is_persisted_for_owner_after_generation(): void {
		$log          = new ArrayObject();
		$persistence  = $this->message_repository( $log );
		$provider     = $this->provider( $log, 'Supported answer. [C1]' );
		$orchestrator = $this->orchestrator( $log, $provider, $persistence );

		$result = $orchestrator->respond(
			new ChatRequest( 'What is supported?', 'model-test', GroundingMode::STRICT, 'conversation-1' ),
			new ChatAccessContext( 'owner-secret', $this->semantic_context(), $this->lexical_filter() )
		);

		self::assertSame( 'Supported answer. [C1]', $result->answer );
		self::assertSame(
			array( 'memory.recent', 'memory.summary', 'retrieval.semantic', 'retrieval.lexical', 'generation', 'persistence' ),
			$log->getArrayCopy()
		);
		self::assertSame( 1, $persistence->append_calls ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double mirrors call count semantics.
		self::assertSame( 'conversation-1', $persistence->conversation_id ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double exposes captured value.
		self::assertSame( 'owner-secret', $persistence->owner_scope ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double exposes captured value.
		self::assertInstanceOf( ConversationMessage::class, $persistence->message );
		self::assertSame( 'assistant', $persistence->message->role );
		self::assertSame( 'Supported answer. [C1]', $persistence->message->content );
		self::assertSame( 1, $provider->generate_calls ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double mirrors call count semantics.
	}

	/**
	 * Failed citation validation must not persist model output.
	 */
	public function test_invalid_citation_output_is_not_persisted(): void {
		$log          = new ArrayObject();
		$persistence  = $this->message_repository( $log );
		$provider     = $this->provider( $log, 'Unsupported answer. [C9]' );
		$orchestrator = $this->orchestrator( $log, $provider, $persistence );

		try {
			$orchestrator->respond(
				new ChatRequest( 'What is supported?', 'model-test', GroundingMode::ASSISTED, 'conversation-1' ),
				new ChatAccessContext( 'owner-secret', $this->semantic_context(), $this->lexical_filter() )
			);
			self::fail( 'Expected invalid citation failure.' );
		} catch ( ChatException $exception ) {
			self::assertSame( 'invalid_citations', $exception->reason->value );
		}

		self::assertSame( 0, $persistence->append_calls ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double mirrors call count semantics.
		self::assertSame( 1, $provider->generate_calls ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double mirrors call count semantics.
	}

	/**
	 * Build the production orchestrator around deterministic test doubles.
	 *
	 * @param ArrayObject        $log Observable call order.
	 * @param GenerationProvider $provider Fake provider.
	 * @param MessageRepository  $persistence Owner-scoped persistence fake.
	 * @phpstan-param ArrayObject<int,string> $log
	 */
	private function orchestrator(
		ArrayObject $log,
		GenerationProvider $provider,
		MessageRepository $persistence
	): ChatOrchestrator {
		$config = new RetrievalConfig();

		return new ChatOrchestrator(
			new ChatRequestPolicy( new QueryPreprocessor( $config ) ),
			new MemoryAssembler( $this->history( $log ) ),
			$this->retriever( $log, $config ),
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			$provider,
			new CitationValidator(),
			$persistence
		);
	}

	/**
	 * Create owner-scoped empty conversation history.
	 *
	 * @param ArrayObject $log Observable call order.
	 * @phpstan-param ArrayObject<int,string> $log
	 */
	private function history( ArrayObject $log ): ConversationHistory {
		return new class( $log ) implements ConversationHistory {
			/**
			 * Create history fake.
			 *
			 * @param ArrayObject $log Observable call order.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log ) {
			}

			/**
			 * Return no recent messages.
			 *
			 * @param string $conversation_id Conversation identifier.
			 * @param string $owner_scope Trusted owner scope.
			 * @param int    $limit Requested limit.
			 * @return list<ConversationMessage>
			 */
			public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array {
				$this->log->append( 'memory.recent' );
				return array();
			}

			/**
			 * Return no summary.
			 *
			 * @param string $conversation_id Conversation identifier.
			 * @param string $owner_scope Trusted owner scope.
			 * @return array{summary:string,version:int}|null
			 */
			public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array {
				$this->log->append( 'memory.summary' );
				return null;
			}
		};
	}

	/**
	 * Build real M10 retrieval around deterministic high-confidence channel fixtures.
	 *
	 * @param ArrayObject     $log Observable call order.
	 * @param RetrievalConfig $config Existing retrieval bounds.
	 * @phpstan-param ArrayObject<int,string> $log
	 */
	private function retriever( ArrayObject $log, RetrievalConfig $config ): HybridRetriever {
		$candidate = new RankedCandidate( 'chunk-1', 'doc-1', 8, 'Selected evidence.', 'en', 'public', 1.0 );
		$semantic  = new class( $log, $candidate ) implements SemanticRetrievalChannel {
			/**
			 * Create semantic fake.
			 *
			 * @param ArrayObject     $log Observable call order.
			 * @param RankedCandidate $candidate Deterministic candidate.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log, private RankedCandidate $candidate ) {
			}

			/**
			 * Return deterministic semantic fixture.
			 *
			 * @param RetrievalQuery           $query Retrieval query.
			 * @param SemanticRetrievalContext $context Trusted semantic context.
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				$this->log->append( 'retrieval.semantic' );
				return array( $this->candidate );
			}
		};
		$lexical   = new class( $log, $candidate ) implements LexicalRetrievalChannel {
			/**
			 * Create lexical fake.
			 *
			 * @param ArrayObject     $log Observable call order.
			 * @param RankedCandidate $candidate Deterministic candidate.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log, private RankedCandidate $candidate ) {
			}

			/**
			 * Return deterministic lexical fixture.
			 *
			 * @param RetrievalQuery $query Retrieval query.
			 * @param LexicalFilter  $filter Trusted lexical filter.
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				$this->log->append( 'retrieval.lexical' );
				return array( $this->candidate );
			}
		};
		$access    = new class() implements CandidateAccessPolicy {
			/**
			 * Permit deterministic fixture already constrained by trusted scope.
			 *
			 * @param RetrievalCandidate $candidate Candidate.
			 * @param RetrievalFilter    $filter Trusted filter.
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
	 * Create a deterministic provider fake.
	 *
	 * @param ArrayObject $log Observable call order.
	 * @param string      $answer Generated answer.
	 * @phpstan-param ArrayObject<int,string> $log
	 */
	private function provider( ArrayObject $log, string $answer ): GenerationProvider {
		return new class( $log, $answer ) implements GenerationProvider {
			/**
			 * Number of generation calls.
			 *
			 * @var int
			 */
			public int $generate_calls = 0;

			/**
			 * Create provider fake.
			 *
			 * @param ArrayObject $log Observable call order.
			 * @param string      $answer Deterministic output.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log, private string $answer ) {
			}

			/** {@inheritDoc} */
			public function provider_id(): string {
				return 'task7-persistence-fake';
			}

			/** {@inheritDoc} */
			public function available(): bool {
				return true;
			}

			/**
			 * Generate deterministic output.
			 *
			 * @param GenerationRequest $request Normalized generation request.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				++$this->generate_calls;
				$this->log->append( 'generation' );
				return new GenerationResult(
					$this->provider_id(),
					$request->model_id,
					$this->answer,
					GenerationStatus::COMPLETED,
					new Usage( 10, 5, 15 )
				);
			}
		};
	}

	/**
	 * Create owner-scoped message persistence fake.
	 *
	 * @param ArrayObject $log Observable call order.
	 * @phpstan-param ArrayObject<int,string> $log
	 */
	private function message_repository( ArrayObject $log ): MessageRepository {
		return new class( $log ) implements MessageRepository {
			/**
			 * Number of append calls.
			 *
			 * @var int
			 */
			public int $append_calls = 0;

			/**
			 * Captured conversation identifier.
			 *
			 * @var string|null
			 */
			public ?string $conversation_id = null;

			/**
			 * Captured trusted owner scope.
			 *
			 * @var string|null
			 */
			public ?string $owner_scope = null;

			/**
			 * Captured assistant message.
			 *
			 * @var ConversationMessage|null
			 */
			public ?ConversationMessage $message = null;

			/**
			 * Create persistence fake.
			 *
			 * @param ArrayObject $log Observable call order.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log ) {
			}

			/**
			 * Capture one owner-scoped assistant message.
			 *
			 * @param string              $conversation_id Conversation identifier.
			 * @param string              $owner_scope Trusted owner scope.
			 * @param ConversationMessage $message Assistant message.
			 */
			public function append_for_owner( string $conversation_id, string $owner_scope, ConversationMessage $message ): void {
				++$this->append_calls;
				$this->conversation_id = $conversation_id;
				$this->owner_scope     = $owner_scope;
				$this->message         = $message;
				$this->log->append( 'persistence' );
			}
		};
	}

	/**
	 * Create trusted semantic scope.
	 */
	private function semantic_context(): SemanticRetrievalContext {
		return new SemanticRetrievalContext(
			new RetrievalFilter( 'public', 'en', array( 8 ) ),
			static fn (): null => null
		);
	}

	/**
	 * Create trusted lexical scope.
	 */
	private function lexical_filter(): LexicalFilter {
		return new LexicalFilter( 'collection-1', null, 8, 'en', 'public' );
	}
}
