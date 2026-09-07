<?php
/**
 * M11 chat analytics wiring tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Chat\ChatAnalyticsEvent;
use WpRagAiChatbot\Chat\ChatAnalyticsHook;
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
 * Proves analytics are safe, ordered, and non-critical to chat success.
 */
final class ChatAnalyticsWiringTest extends TestCase {
	/**
	 * Successful generation emits exactly one safe analytics event after persistence.
	 */
	public function test_success_emits_text_free_analytics_after_persistence(): void {
		$log    = new ArrayObject();
		$events = new ArrayObject();
		$hook   = $this->hook( $log, $events );
		$result = $this->respond( $log, $hook, true );

		self::assertFalse( $result->no_answer );
		self::assertSame( array( 'generation', 'persistence', 'analytics' ), $log->getArrayCopy() );
		self::assertCount( 1, $events );

		$event = $events[0];
		self::assertInstanceOf( ChatAnalyticsEvent::class, $event );
		self::assertSame(
			array(
				'no_answer',
				'model_id',
				'provider_id',
				'retrieval_candidate_count',
				'citation_count',
				'input_tokens',
				'output_tokens',
				'total_tokens',
			),
			array_keys( get_object_vars( $event ) )
		);
		self::assertFalse( $event->no_answer );
		self::assertSame( 'model-test', $event->model_id );
		self::assertSame( 'analytics-fake', $event->provider_id );
		self::assertSame( 1, $event->retrieval_candidate_count );
		self::assertSame( 1, $event->citation_count );
		self::assertSame( 10, $event->input_tokens );
		self::assertSame( 5, $event->output_tokens );
		self::assertSame( 15, $event->total_tokens );
	}

	/**
	 * Strict deterministic no-answer emits safe analytics without generation or persistence.
	 */
	public function test_strict_no_answer_emits_analytics_without_generation(): void {
		$log    = new ArrayObject();
		$events = new ArrayObject();
		$result = $this->respond( $log, $this->hook( $log, $events ), false );

		self::assertTrue( $result->no_answer );
		self::assertSame( array( 'analytics' ), $log->getArrayCopy() );
		self::assertCount( 1, $events );
		$event = $events[0];
		self::assertInstanceOf( ChatAnalyticsEvent::class, $event );
		self::assertTrue( $event->no_answer );
		self::assertNull( $event->provider_id );
		self::assertSame( 0, $event->citation_count );
		self::assertNull( $event->total_tokens );
	}

	/**
	 * Analytics transport failures are contained after a valid persisted answer.
	 */
	public function test_analytics_failure_cannot_break_valid_chat_result(): void {
		$log  = new ArrayObject();
		$hook = new class( $log ) implements ChatAnalyticsHook {
			/**
			 * Store the observable event order.
			 *
			 * @param ArrayObject $log Observable order.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log ) {
			}

			/**
			 * Record an analytics event and simulate a transport failure.
			 *
			 * @param ChatAnalyticsEvent $event Analytics event.
			 *
			 * @throws RuntimeException Always, to verify containment.
			 */
			public function record( ChatAnalyticsEvent $event ): void {
				$this->log->append( 'analytics' );
				throw new RuntimeException( 'analytics api_key=secret raw body' );
			}
		};

		$result = $this->respond( $log, $hook, true );

		self::assertFalse( $result->no_answer );
		self::assertSame( 'Grounded answer. [C1]', $result->answer );
		self::assertSame( array( 'generation', 'persistence', 'analytics' ), $log->getArrayCopy() );
	}

	/**
	 * Invalid analytics metadata cannot break a valid persisted answer.
	 */
	public function test_invalid_analytics_metadata_cannot_break_valid_chat_result(): void {
		$log    = new ArrayObject();
		$events = new ArrayObject();
		$result = $this->respond( $log, $this->hook( $log, $events ), true, '' );

		self::assertFalse( $result->no_answer );
		self::assertSame( 'Grounded answer. [C1]', $result->answer );
		self::assertSame( array( 'generation', 'persistence' ), $log->getArrayCopy() );
		self::assertCount( 0, $events );
	}

	/**
	 * Build and execute the real application composition with deterministic external boundaries.
	 *
	 * @param ArrayObject       $log Observable order.
	 * @param ChatAnalyticsHook $hook Analytics hook under test.
	 * @param bool              $with_evidence Whether retrieval returns one selected candidate.
	 * @param string            $provider_id Provider result identifier.
	 * @phpstan-param ArrayObject<int,string> $log
	 */
	private function respond(
		ArrayObject $log,
		ChatAnalyticsHook $hook,
		bool $with_evidence,
		string $provider_id = 'analytics-fake'
	): \WpRagAiChatbot\Chat\ChatResult {
		$config      = new RetrievalConfig();
		$candidate   = new RankedCandidate( 'chunk-1', 'doc-1', 8, 'Grounded evidence.', 'en', 'public', 1.0 );
		$fixtures    = $with_evidence ? array( $candidate ) : array();
		$history     = new class() implements ConversationHistory {
			/**
			 * Return recent owner-scoped history.
			 *
			 * @param string $conversation_id Conversation identifier.
			 * @param string $owner_scope Owner scope.
			 * @param int    $limit Maximum messages.
			 *
			 * @return array<int,ConversationMessage>
			 */
			public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array {
				return array();
			}

			/**
			 * Return an owner-scoped summary.
			 *
			 * @param string $conversation_id Conversation identifier.
			 * @param string $owner_scope Owner scope.
			 *
			 * @return array<string,mixed>|null
			 */
			public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array {
				return null;
			}
		};
		$provider = new class( $log, $provider_id ) implements GenerationProvider {
			/**
			 * Store the observable event order and provider ID.
			 *
			 * @param ArrayObject $log Observable order.
			 * @param string      $provider_id Provider identifier.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log, private string $provider_id ) {
			}

			/**
			 * Return the deterministic provider identifier.
			 */
			public function provider_id(): string {
				return $this->provider_id;
			}

			/**
			 * Report that the fake provider is available.
			 */
			public function available(): bool {
				return true;
			}

			/**
			 * Generate the deterministic grounded answer.
			 *
			 * @param GenerationRequest $request Generation request.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				$this->log->append( 'generation' );
				return new GenerationResult(
					$this->provider_id(),
					$request->model_id,
					'Grounded answer. [C1]',
					GenerationStatus::COMPLETED,
					new Usage( 10, 5, 15 )
				);
			}
		};
		$persistence = new class( $log ) implements MessageRepository {
			/**
			 * Store the observable event order.
			 *
			 * @param ArrayObject $log Observable order.
			 * @phpstan-param ArrayObject<int,string> $log
			 */
			public function __construct( private ArrayObject $log ) {
			}

			/**
			 * Persist an owner-scoped message.
			 *
			 * @param string              $conversation_id Conversation identifier.
			 * @param string              $owner_scope Owner scope.
			 * @param ConversationMessage $message Conversation message.
			 */
			public function append_for_owner( string $conversation_id, string $owner_scope, ConversationMessage $message ): void {
				$this->log->append( 'persistence' );
			}
		};

		$orchestrator = ( new ReflectionClass( ChatOrchestrator::class ) )->newInstance(
			new ChatRequestPolicy( new QueryPreprocessor( $config ) ),
			new MemoryAssembler( $history ),
			$this->retriever( $fixtures, $config ),
			new DeterministicGroundingPolicy(),
			new PromptBuilder(),
			$provider,
			new CitationValidator(),
			$persistence,
			$hook
		);
		self::assertInstanceOf( ChatOrchestrator::class, $orchestrator );

		return $orchestrator->respond(
			new ChatRequest( 'What does the source say?', 'model-test', GroundingMode::STRICT, 'conversation-1' ),
			new ChatAccessContext(
				'owner-secret-not-analytics',
				new SemanticRetrievalContext( new RetrievalFilter( 'public', 'en', array( 8 ) ), static fn (): null => null ),
				new LexicalFilter( 'collection-1', null, 8, 'en', 'public' )
			)
		);
	}

	/**
	 * Build a deterministic analytics collector.
	 *
	 * @param ArrayObject $log Observable order.
	 * @param ArrayObject $events Captured events.
	 * @phpstan-param ArrayObject<int,string> $log
	 * @phpstan-param ArrayObject<int,ChatAnalyticsEvent> $events
	 */
	private function hook( ArrayObject $log, ArrayObject $events ): ChatAnalyticsHook {
		return new class( $log, $events ) implements ChatAnalyticsHook {
			/**
			 * Store observable order and captured analytics events.
			 *
			 * @param ArrayObject $log Observable order.
			 * @param ArrayObject $events Captured events.
			 * @phpstan-param ArrayObject<int,string> $log
			 * @phpstan-param ArrayObject<int,ChatAnalyticsEvent> $events
			 */
			public function __construct( private ArrayObject $log, private ArrayObject $events ) {
			}

			/**
			 * Record the analytics event.
			 *
			 * @param ChatAnalyticsEvent $event Analytics event.
			 */
			public function record( ChatAnalyticsEvent $event ): void {
				$this->log->append( 'analytics' );
				$this->events->append( $event );
			}
		};
	}

	/**
	 * Build real M10 retrieval around deterministic selected fixtures.
	 *
	 * @param array           $fixtures Ranked fixtures.
	 * @param RetrievalConfig $config Retrieval bounds.
	 * @phpstan-param list<RankedCandidate> $fixtures
	 */
	private function retriever( array $fixtures, RetrievalConfig $config ): HybridRetriever {
		$semantic = new class( $fixtures ) implements SemanticRetrievalChannel {
			/**
			 * Store deterministic ranked fixtures.
			 *
			 * @param array $fixtures Ranked fixtures.
			 * @phpstan-param list<RankedCandidate> $fixtures
			 */
			public function __construct( private array $fixtures ) {
			}

			/**
			 * Return deterministic semantic fixtures.
			 *
			 * @param RetrievalQuery           $query Retrieval query.
			 * @param SemanticRetrievalContext $context Semantic context.
			 *
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array {
				return $this->fixtures;
			}
		};
		$lexical  = new class( $fixtures ) implements LexicalRetrievalChannel {
			/**
			 * Store deterministic ranked fixtures.
			 *
			 * @param array $fixtures Ranked fixtures.
			 * @phpstan-param list<RankedCandidate> $fixtures
			 */
			public function __construct( private array $fixtures ) {
			}

			/**
			 * Return deterministic lexical fixtures.
			 *
			 * @param RetrievalQuery $query Retrieval query.
			 * @param LexicalFilter  $filter Lexical filter.
			 *
			 * @return list<RankedCandidate>
			 */
			public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
				return $this->fixtures;
			}
		};
		$access   = new class() implements CandidateAccessPolicy {
			/**
			 * Allow the deterministic test candidate.
			 *
			 * @param RetrievalCandidate $candidate Retrieval candidate.
			 * @param RetrievalFilter    $filter Retrieval filter.
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
}
