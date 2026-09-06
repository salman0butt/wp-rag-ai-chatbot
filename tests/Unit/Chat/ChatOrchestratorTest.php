<?php
/**
 * Non-streaming chat orchestration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use WpRagAiChatbot\Chat\ChatFailureReason;
use WpRagAiChatbot\Chat\ChatRequest;
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
use WpRagAiChatbot\Citations\CitationValidator;

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
		$log      = new Task7EventLog();
		$provider = new Task7GenerationProvider( $log, 'unused [C1]' );
		$result   = $this->respond(
			new ChatRequest( 'What is the refund policy?', 'model-test', GroundingMode::STRICT, 'conversation-1' ),
			$log,
			$provider,
			array(),
			array()
		);

		self::assertTrue( $this->property( $result, 'no_answer' ) );
		self::assertSame( "I don't have enough reliable information in the selected sources to answer that.", $this->property( $result, 'answer' ) );
		self::assertSame( 0, $provider->generate_calls );
		self::assertSame( array( 'memory.recent', 'memory.summary', 'retrieval.semantic', 'retrieval.lexical' ), $log->events );
	}

	/**
	 * Successful orchestration uses bounded collaborators once and keeps trusted owner scope out of model input.
	 */
	public function test_success_calls_generation_once_and_excludes_owner_scope_from_prompt(): void {
		$log          = new Task7EventLog();
		$provider     = new Task7GenerationProvider( $log, 'Refunds are available within 30 days. [C1]' );
		$owner_secret = 'owner-secret-do-not-send';
		$candidate    = $this->ranked( 'refund-policy', 1.0 );
		$result       = $this->respond(
			new ChatRequest( 'What is the refund policy?', 'model-test', GroundingMode::STRICT, 'conversation-1' ),
			$log,
			$provider,
			array( $candidate ),
			array( $candidate ),
			$owner_secret
		);

		self::assertFalse( $this->property( $result, 'no_answer' ) );
		self::assertSame( 'Refunds are available within 30 days. [C1]', $this->property( $result, 'answer' ) );
		self::assertCount( 1, $this->property( $result, 'citations' ) );
		self::assertSame( 1, $provider->generate_calls );
		self::assertNotNull( $provider->last_request );
		self::assertStringNotContainsString( $owner_secret, $provider->last_request->input );
		self::assertStringNotContainsString( $owner_secret, (string) $provider->last_request->instructions );
		self::assertSame(
			array( 'memory.recent', 'memory.summary', 'retrieval.semantic', 'retrieval.lexical', 'generation' ),
			$log->events
		);
	}

	/**
	 * Unknown model-authored citation IDs fail closed after generation.
	 */
	public function test_invalid_citations_map_to_stable_failure_reason(): void {
		$log       = new Task7EventLog();
		$provider  = new Task7GenerationProvider( $log, 'Invented source. [C9]' );
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
		self::assertSame( 1, $provider->generate_calls );
	}

	/**
	 * Retrieval failures expose only the stable application reason and never the raw source error.
	 */
	public function test_retrieval_failure_is_sanitized(): void {
		$log      = new Task7EventLog();
		$provider = new Task7GenerationProvider( $log, 'unused' );

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
			self::assertSame( ChatFailureReason::RETRIEVAL_UNAVAILABLE, $this->property( $exception, 'reason' ) );
			self::assertStringNotContainsString( 'secret response body', $exception->getMessage() );
		}

		self::assertSame( 0, $provider->generate_calls );
	}

	/**
	 * Provider failure is normalized and raw provider diagnostics remain internal.
	 */
	public function test_provider_failure_is_sanitized(): void {
		$log       = new Task7EventLog();
		$provider  = new Task7GenerationProvider( $log, '', true, new RuntimeException( 'provider api-key leak' ) );
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
			self::assertSame( ChatFailureReason::GENERATION_FAILED, $this->property( $exception, 'reason' ) );
			self::assertStringNotContainsString( 'api-key', $exception->getMessage() );
		}
	}

	/**
	 * Invoke the future Task 7 orchestrator without a compile-time dependency before RED is proven.
	 *
	 * @param ChatRequest                    $request Normalized chat request.
	 * @param Task7EventLog                  $log Observable deterministic collaborator order.
	 * @param Task7GenerationProvider        $provider Fake generation provider.
	 * @param array|RuntimeException         $semantic Semantic fixtures or failure.
	 * @param array|RuntimeException         $lexical Lexical fixtures or failure.
	 * @param string                         $owner_scope Trusted owner scope.
	 * @phpstan-param list<RankedCandidate>|RuntimeException $semantic
	 * @phpstan-param list<RankedCandidate>|RuntimeException $lexical
	 */
	private function respond(
		ChatRequest $request,
		Task7EventLog $log,
		Task7GenerationProvider $provider,
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

		$config = new RetrievalConfig();
		$policy = ( new ReflectionClass( $policy_class ) )->newInstance( new QueryPreprocessor( $config ) );
		$memory = new MemoryAssembler( new Task7ConversationHistory( $log ) );
		$access = ( new ReflectionClass( $access_class ) )->newInstance(
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
		$result = ( new ReflectionMethod( $orchestrator_class, 'respond' ) )->invoke( $orchestrator, $request, $access );
		self::assertIsObject( $result );

		return $result;
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
			self::assertSame( $reason, $this->property( $exception, 'reason' ) );
		}
	}

	/**
	 * Read one public property without statically depending on a RED contract.
	 *
	 * @param object $object Object under test.
	 * @param string $name Property name.
	 * @return mixed
	 */
	private function property( object $object, string $name ): mixed {
		return ( new ReflectionProperty( $object, $name ) )->getValue( $object );
	}

	/**
	 * Build the real M10 retriever around deterministic fake channels.
	 *
	 * @param Task7EventLog          $log Observable collaborator order.
	 * @param array|RuntimeException $semantic Semantic fixtures or failure.
	 * @param array|RuntimeException $lexical Lexical fixtures or failure.
	 * @param RetrievalConfig        $config Retrieval bounds.
	 * @phpstan-param list<RankedCandidate>|RuntimeException $semantic
	 * @phpstan-param list<RankedCandidate>|RuntimeException $lexical
	 */
	private function retriever(
		Task7EventLog $log,
		array|RuntimeException $semantic,
		array|RuntimeException $lexical,
		RetrievalConfig $config
	): HybridRetriever {
		$access_policy = new class() implements CandidateAccessPolicy {
			/**
			 * Permit deterministic fixtures already constrained by trusted scope.
			 *
			 * @param RetrievalCandidate $candidate Candidate.
			 * @param RetrievalFilter    $filter Trusted filter.
			 */
			public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
				return true;
			}
		};

		return new HybridRetriever(
			new Task7SemanticChannel( $log, $semantic ),
			new Task7LexicalChannel( $log, $lexical ),
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			$access_policy,
			$config
		);
	}

	/**
	 * Create one ranked fixture used by both retrieval channels.
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

/**
 * Mutable event ledger for deterministic call-order assertions.
 */
final class Task7EventLog {
	/** @var list<string> */
	public array $events = array();
}

/**
 * Owner-scoped memory fake.
 */
final class Task7ConversationHistory implements ConversationHistory {
	public function __construct( private Task7EventLog $log ) {
	}

	/** {@inheritDoc} */
	public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array {
		$this->log->events[] = 'memory.recent';
		return array();
	}

	/** {@inheritDoc} */
	public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array {
		$this->log->events[] = 'memory.summary';
		return null;
	}
}

/**
 * Deterministic semantic channel fake.
 */
final class Task7SemanticChannel implements SemanticRetrievalChannel {
	/**
	 * @param array|RuntimeException $result Configured result or failure.
	 * @phpstan-param list<RankedCandidate>|RuntimeException $result
	 */
	public function __construct( private Task7EventLog $log, private array|RuntimeException $result ) {
	}

	/** {@inheritDoc} */
	public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
		$this->log->events[] = 'retrieval.semantic';
		if ( $this->result instanceof RuntimeException ) {
			throw $this->result;
		}
		return $this->result;
	}
}

/**
 * Deterministic lexical channel fake.
 */
final class Task7LexicalChannel implements LexicalRetrievalChannel {
	/**
	 * @param array|RuntimeException $result Configured result or failure.
	 * @phpstan-param list<RankedCandidate>|RuntimeException $result
	 */
	public function __construct( private Task7EventLog $log, private array|RuntimeException $result ) {
	}

	/** {@inheritDoc} */
	public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
		$this->log->events[] = 'retrieval.lexical';
		if ( $this->result instanceof RuntimeException ) {
			throw $this->result;
		}
		return $this->result;
	}
}

/**
 * Deterministic generation provider fake.
 */
final class Task7GenerationProvider implements GenerationProvider {
	public int $generate_calls = 0;
	public ?GenerationRequest $last_request = null;

	public function __construct(
		private Task7EventLog $log,
		private string $answer,
		private bool $available = true,
		private ?RuntimeException $failure = null
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

	/** {@inheritDoc} */
	public function generate( GenerationRequest $request ): GenerationResult {
		++$this->generate_calls;
		$this->last_request  = $request;
		$this->log->events[] = 'generation';
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
}
