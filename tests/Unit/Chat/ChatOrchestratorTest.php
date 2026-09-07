<?php
/**
 * Non-streaming chat orchestration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use WpRagAiChatbot\Chat\ChatFailureReason;
use WpRagAiChatbot\Chat\ChatRequest;
use WpRagAiChatbot\Citations\CitationValidator;
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
 * Specifies provider-neutral Task 7 orchestration and safe failure behavior.
 */
final class ChatOrchestratorTest extends TestCase {
	/**
	 * Task 7 contracts must exist before orchestration can run.
	 */
	public function test_task_seven_contracts_exist(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\ChatAccessContext' ), 'ChatAccessContext contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\ChatRequestPolicy' ), 'ChatRequestPolicy contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\ChatException' ), 'ChatException contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\ChatOrchestrator' ), 'ChatOrchestrator contract is missing.' );
	}

	/**
	 * Strict insufficient evidence returns the deterministic no-answer without provider generation.
	 */
	public function test_strict_grounding_denial_skips_generation_entirely(): void {
		$log      = new ArrayObject();
		$provider = $this->provider( $log, 'unused [C1]' );
		$result   = $this->respond(
			new ChatRequest( 'What is the refund policy?', 'model-test', GroundingMode::STRICT, 'conversation-1' ),
			$log,
			$provider,
			array(),
			array()
		);

		self::assertTrue( $this->property_value( $result, 'no_answer' ) );
		self::assertSame( "I don't have enough reliable information in the selected sources to answer that.", $this->property_value( $result, 'answer' ) );
		self::assertSame( 0, $this->property_value( $provider, 'generate_calls' ) );
		self::assertSame( array( 'memory.recent', 'memory.summary', 'retrieval.semantic', 'retrieval.lexical' ), $log->getArrayCopy() );
	}

	/**
	 * Successful orchestration uses bounded collaborators once and keeps trusted owner scope out of model input.
	 */
	public function test_success_calls_generation_once_and_excludes_owner_scope_from_prompt(): void {
		$log                = new ArrayObject();
		$provider           = $this->provider( $log, 'Refunds are available within 30 days. [C1]' );
		$owner_secret       = 'owner-secret-do-not-send';
		$candidate          = $this->ranked( 'refund-policy', 1.0 );
		$result             = $this->respond(
			new ChatRequest( 'What is the refund policy?', 'model-test', GroundingMode::STRICT, 'conversation-1' ),
			$log,
			$provider,
			array( $candidate ),
			array( $candidate ),
			$owner_secret
		);
		$generation_request = $this->property_value( $provider, 'last_request' );

		self::assertFalse( $this->property_value( $result, 'no_answer' ) );
		self::assertSame( 'Refunds are available within 30 days. [C1]', $this->property_value( $result, 'answer' ) );
		self::assertCount( 1, $this->property_value( $result, 'citations' ) );
		self::assertSame( 1, $this->property_value( $provider, 'generate_calls' ) );
		self::assertInstanceOf( GenerationRequest::class, $generation_request );
		self::assertStringNotContainsString( $owner_secret, $generation_request->input );
		self::assertStringNotContainsString( $owner_secret, (string) $generation_request->instructions );
		self::assertSame(
			array( 'memory.recent', 'memory.summary', 'retrieval.semantic', 'retrieval.lexical', 'generation' ),
			$log->getArrayCopy()
		);
	}

	/**
	 * Unknown model-authored citation IDs fail closed after generation.
	 */
	public function test_invalid_citations_map_to_stable_failure_reason(): void {
		$log       = new ArrayObject();
		$provider  = $this->provider( $log, 'Invented source. [C9]' );
		$candidate = $this->ranked( 'refund-policy', 1.0 );

		$this->assert_failure_reason(
			ChatFailureReason::INVALID_CITATIONS,
			fn (): object => $this->respond(
				new ChatRequest( 'What is the refund policy?', 'model-test', GroundingMode::ASSISTED, 'conversation-1' ),
				$log,
				$provider,
				array( $candidate ),
				array( $candidate )
			)
		);
		self::assertSame( 1, $this->property_value( $provider, 'generate_calls' ) );
	}

	/**
	 * Retrieval failures expose only the stable application reason and never the raw source error.
	 */
	public function test_retrieval_failure_is_sanitized(): void {
		$log      = new ArrayObject();
		$provider = $this->provider( $log, 'unused' );

		try {
			$this->respond(
				new ChatRequest( 'What is the refund policy?', 'model-test', GroundingMode::ASSISTED, 'conversation-1' ),
				$log,
				$provider,
				new RuntimeException( 'semantic secret response body' ),
				new RuntimeException( 'lexical secret response body' )
			);
			self::fail( 'Expected a sanitized chat failure.' );
		} catch ( Throwable $exception ) {
			self::assertSame( 'WpRagAiChatbot\\Chat\\ChatException', $exception::class );
			self::assertSame( ChatFailureReason::RETRIEVAL_UNAVAILABLE, $this->property_value( $exception, 'reason' ) );
			self::assertStringNotContainsString( 'secret response body', $exception->getMessage() );
		}

		self::assertSame( 0, $this->property_value( $provider, 'generate_calls' ) );
	}

	/**
	 * Provider failure is normalized and raw provider diagnostics remain internal.
	 */
	public function test_provider_failure_is_sanitized(): void {
		$log       = new ArrayObject();
		$provider  = $this->provider( $log, '', true, new RuntimeException( 'provider api-key leak' ) );
		$candidate = $this->ranked( 'refund-policy', 1.0 );

		try {
			$this->respond(
				new ChatRequest( 'What is the refund policy?', 'model-test', GroundingMode::ASSISTED, 'conversation-1' ),
				$log,
				$provider,
				array( $candidate ),
				array( $candidate )
			);
			self::fail( 'Expected a sanitized generation failure.' );
		} catch ( Throwable $exception ) {
			self::assertSame( 'WpRagAiChatbot\\Chat\\ChatException', $exception::class );
			self::assertSame( ChatFailureReason::GENERATION_FAILED, $this->property_value( $exception, 'reason' ) );
			self::assertStringNotContainsString( 'api-key', $exception->getMessage() );
		}
	}

	/**
	 * Invoke the future Task 7 orchestrator without a compile-time dependency before RED is proven.
	 *
	 * @param ChatRequest            $request Normalized chat request.
	 * @param ArrayObject            $log Observable collaborator order.
	 * @param GenerationProvider     $provider Fake generation provider.
	 * @param array|RuntimeException $semantic Semantic fixtures or failure.
	 * @param array|RuntimeException $lexical Lexical fixtures or failure.
	 * @param string                 $owner_scope Trusted owner scope.
	 * @phpstan-param ArrayObject<int,string> $log
	 * @phpstan-param list<RankedCandidate>|RuntimeException $semantic
	 * @phpstan-param list<RankedCandidate>|RuntimeException $lexical
	 */
	private function respond(
		ChatRequest $request,
		ArrayObject $log,
		GenerationProvider $provider,
		array|RuntimeException $semantic,
		array|RuntimeException $lexical,
		string $owner_scope = 'owner-1'
	): object {
		$orchestrator_class = 'WpRagAiChatbot\\Chat\\ChatOrchestrator';
		$access_class       = 'WpRagAiChatbot\\Chat\\ChatAccessContext';
		$policy_class       = 'WpRagAiChatbot\\Chat\\ChatRequestPolicy';
		self::assertTrue( class_exists( $orchestrator_class ), 'ChatOrchestrator contract is missing.' );
		self::assertTrue( class_exists( $access_class ), 'ChatAccessContext contract is missing.' );
		self::assertTrue( class_exists( $policy_class ), 'ChatRequestPolicy contract is missing.' );

		$config       = new RetrievalConfig();
		$policy       = ( new ReflectionClass( $policy_class ) )->newInstance( new QueryPreprocessor( $config ) );
		$history      = new class( $log ) implements ConversationHistory {
			/**
			 * Create deterministic owner-scoped history.
			 *
			 * @param ArrayObject $log Observable collaborator order.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log ) {
			}

			/**
			 * Return no messages while recording owner-scoped access.
			 *
			 * @param string $conversation_id Stable conversation identifier.
			 * @param string $owner_scope Trusted owner scope.
			 * @param int    $limit Hard recent-message limit.
			 * @return list<\WpRagAiChatbot\Conversations\ConversationMessage>
			 */
			public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array {
				$this->log->append( 'memory.recent' );
				return array();
			}

			/**
			 * Return no summary while recording owner-scoped access.
			 *
			 * @param string $conversation_id Stable conversation identifier.
			 * @param string $owner_scope Trusted owner scope.
			 * @return array{version:int,text:string}|null
			 */
			public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array {
				$this->log->append( 'memory.summary' );
				return null;
			}
		};
		$memory       = new MemoryAssembler( $history );
		$access       = ( new ReflectionClass( $access_class ) )->newInstance(
			$owner_scope,
			$this->semantic_context(),
			$this->lexical_filter(),
			false
		);
		$orchestrator = ( new ReflectionClass( $orchestrator_class ) )->newInstance(
			$policy,
			$memory,
			$this->retriever( $log, $semantic, $lexical, $config ),
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			$provider,
			new CitationValidator()
		);
		$result       = ( new ReflectionMethod( $orchestrator_class, 'respond' ) )->invoke( $orchestrator, $request, $access );
		self::assertIsObject( $result );

		return $result;
	}

	/**
	 * Create one deterministic provider fake.
	 *
	 * @param ArrayObject           $log Observable collaborator order.
	 * @param string                $answer Generated answer.
	 * @param bool                  $available Provider availability.
	 * @param RuntimeException|null $failure Optional generated failure.
	 * @phpstan-param ArrayObject<int,string> $log
	 */
	private function provider(
		ArrayObject $log,
		string $answer,
		bool $available = true,
		?RuntimeException $failure = null
	): GenerationProvider {
		return new class( $log, $answer, $available, $failure ) implements GenerationProvider {
			/**
			 * Number of generation calls.
			 *
			 * @var int
			 */
			public int $generate_calls = 0;

			/**
			 * Last normalized generation request.
			 *
			 * @var GenerationRequest|null
			 */
			public ?GenerationRequest $last_request = null;

			/**
			 * Create deterministic provider fake.
			 *
			 * @param ArrayObject           $log Observable collaborator order.
			 * @param string                $answer Generated answer.
			 * @param bool                  $available Provider availability.
			 * @param RuntimeException|null $failure Optional failure.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct(
				private ArrayObject $log,
				private string $answer,
				private bool $available,
				private ?RuntimeException $failure
			) {
			}

			/** {@inheritDoc} */
			public function provider_id(): string {
				return 'task7-fake';
			}

			/** {@inheritDoc} */
			public function available(): bool {
				return $this->available;
			}

			/**
			 * Generate the configured deterministic response.
			 *
			 * @param GenerationRequest $request Normalized generation request.
			 * @throws RuntimeException When a configured provider failure exists.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				++$this->generate_calls;
				$this->last_request = $request;
				$this->log->append( 'generation' );
				if ( null !== $this->failure ) {
					throw $this->failure;
				}

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
	 * Assert a Task 7 exception carries exactly one stable safe reason.
	 *
	 * @param ChatFailureReason $reason Expected stable reason.
	 * @param callable          $operation Operation expected to fail.
	 * @phpstan-param callable(): object $operation
	 */
	private function assert_failure_reason( ChatFailureReason $reason, callable $operation ): void {
		try {
			$operation();
			self::fail( 'Expected Task 7 failure.' );
		} catch ( Throwable $exception ) {
			self::assertSame( 'WpRagAiChatbot\\Chat\\ChatException', $exception::class );
			self::assertSame( $reason, $this->property_value( $exception, 'reason' ) );
		}
	}

	/**
	 * Read one public property without statically depending on a RED contract.
	 *
	 * @param object $subject Object under test.
	 * @param string $name Property name.
	 * @return mixed
	 */
	private function property_value( object $subject, string $name ): mixed {
		return ( new ReflectionProperty( $subject, $name ) )->getValue( $subject );
	}

	/**
	 * Build the real M10 retriever around deterministic fake channels.
	 *
	 * @param ArrayObject            $log Observable collaborator order.
	 * @param array|RuntimeException $semantic Semantic fixtures or failure.
	 * @param array|RuntimeException $lexical Lexical fixtures or failure.
	 * @param RetrievalConfig        $config Retrieval bounds.
	 * @phpstan-param ArrayObject<int,string> $log
	 * @phpstan-param list<RankedCandidate>|RuntimeException $semantic
	 * @phpstan-param list<RankedCandidate>|RuntimeException $lexical
	 */
	private function retriever(
		ArrayObject $log,
		array|RuntimeException $semantic,
		array|RuntimeException $lexical,
		RetrievalConfig $config
	): HybridRetriever {
		$semantic_channel = new class( $log, $semantic ) implements SemanticRetrievalChannel {
			/**
			 * Create semantic fake.
			 *
			 * @param ArrayObject            $log Observable collaborator order.
			 * @param array|RuntimeException $result Result or failure.
			 * @phpstan-param ArrayObject<int,string> $log
			 * @phpstan-param list<RankedCandidate>|RuntimeException $result
			 */
			public function __construct( private ArrayObject $log, private array|RuntimeException $result ) {
			}

			/**
			 * Return configured semantic fixtures.
			 *
			 * @param RetrievalQuery           $query Retrieval query.
			 * @param SemanticRetrievalContext $context Trusted semantic context.
			 * @return list<RankedCandidate>
			 * @throws RuntimeException When configured failure exists.
			 */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				$this->log->append( 'retrieval.semantic' );
				if ( $this->result instanceof RuntimeException ) {
					throw $this->result;
				}
				return $this->result;
			}
		};
		$lexical_channel  = new class( $log, $lexical ) implements LexicalRetrievalChannel {
			/**
			 * Create lexical fake.
			 *
			 * @param ArrayObject            $log Observable collaborator order.
			 * @param array|RuntimeException $result Result or failure.
			 * @phpstan-param ArrayObject<int,string> $log
			 * @phpstan-param list<RankedCandidate>|RuntimeException $result
			 */
			public function __construct( private ArrayObject $log, private array|RuntimeException $result ) {
			}

			/**
			 * Return configured lexical fixtures.
			 *
			 * @param RetrievalQuery $query Retrieval query.
			 * @param LexicalFilter  $filter Trusted lexical filter.
			 * @return list<RankedCandidate>
			 * @throws RuntimeException When configured failure exists.
			 */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				$this->log->append( 'retrieval.lexical' );
				if ( $this->result instanceof RuntimeException ) {
					throw $this->result;
				}
				return $this->result;
			}
		};
		$access_policy    = new class() implements CandidateAccessPolicy {
			/**
			 * Permit fixtures already constrained by trusted scope.
			 *
			 * @param RetrievalCandidate $candidate Candidate.
			 * @param RetrievalFilter    $filter Trusted filter.
			 */
			public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				return true;
			}
		};

		return new HybridRetriever(
			$semantic_channel,
			$lexical_channel,
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			$access_policy,
			$config
		);
	}

	/**
	 * Create one ranked fixture used by both retrieval channels.
	 *
	 * @param string $id Stable chunk ID.
	 * @param float  $score Native channel score.
	 */
	private function ranked( string $id, float $score ): RankedCandidate {
		return new RankedCandidate( $id, 'doc-' . $id, 8, 'Selected evidence.', 'en', 'public', $score );
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
