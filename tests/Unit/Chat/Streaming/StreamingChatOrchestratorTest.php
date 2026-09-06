<?php
/**
 * Streaming orchestration tests for M11 Task 8.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat\Streaming;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Chat\ChatFailureReason;
use WpRagAiChatbot\Chat\Streaming\Cancellation;
use WpRagAiChatbot\Chat\Streaming\GenerationStream;
use WpRagAiChatbot\Chat\Streaming\StreamEvent;
use WpRagAiChatbot\Chat\Streaming\StreamEventType;
use WpRagAiChatbot\Chat\Streaming\StreamingChatOrchestrator;
use WpRagAiChatbot\Citations\CitationRegistry;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Proves deterministic event ordering, bounds, citation validation, errors, and cancellation cleanup.
 */
final class StreamingChatOrchestratorTest extends TestCase {
	/**
	 * Valid output emits ordered deltas, trusted citations, and one terminal completion.
	 */
	public function test_valid_stream_emits_monotonic_normalized_events(): void {
		$stream = $this->stream( array( 'Hello ', 'world [C1]' ) );
		$events = iterator_to_array(
			( new StreamingChatOrchestrator() )->stream( $stream, $this->registry(), new Cancellation() ),
			false
		);

		self::assertSame(
			array(
				StreamEventType::MESSAGE_START,
				StreamEventType::MESSAGE_DELTA,
				StreamEventType::MESSAGE_DELTA,
				StreamEventType::CITATION,
				StreamEventType::MESSAGE_COMPLETE,
			),
			array_map( static fn ( StreamEvent $event ): StreamEventType => $event->type, $events )
		);
		self::assertSame( array( 0, 1, 2, 3, 4 ), array_map( static fn ( StreamEvent $event ): int => $event->sequence, $events ) );
		self::assertSame( 'C1', $events[3]->citation_id );
		self::assertTrue( $stream->closed );
	}

	/**
	 * Oversized provider deltas are split before client-visible emission.
	 */
	public function test_oversized_delta_is_split_into_bounded_events(): void {
		$text   = str_repeat( 'a', 5000 );
		$stream = $this->stream( array( $text ) );
		$events = iterator_to_array(
			( new StreamingChatOrchestrator() )->stream( $stream, CitationRegistry::from_candidates( array() ), new Cancellation() ),
			false
		);

		$deltas = array_values(
			array_filter( $events, static fn ( StreamEvent $event ): bool => StreamEventType::MESSAGE_DELTA === $event->type )
		);
		self::assertCount( 2, $deltas );
		self::assertSame( $text, implode( '', array_map( static fn ( StreamEvent $event ): string => (string) $event->text, $deltas ) ) );
		foreach ( $deltas as $delta ) {
			self::assertLessThanOrEqual( 4096, strlen( (string) $delta->text ) );
		}
		self::assertSame( StreamEventType::MESSAGE_COMPLETE, $events[ count( $events ) - 1 ]->type );
	}

	/**
	 * Provider failures are normalized without leaking raw exception text.
	 */
	public function test_provider_exception_emits_one_sanitized_terminal_error(): void {
		$stream = $this->throwing_stream();
		$events = iterator_to_array(
			( new StreamingChatOrchestrator() )->stream( $stream, CitationRegistry::from_candidates( array() ), new Cancellation() ),
			false
		);

		self::assertCount( 2, $events );
		self::assertSame( StreamEventType::MESSAGE_START, $events[0]->type );
		self::assertSame( StreamEventType::ERROR, $events[1]->type );
		self::assertSame( ChatFailureReason::GENERATION_FAILED, $events[1]->error_reason );
		self::assertNull( $events[1]->text );
		self::assertTrue( $stream->closed );
	}

	/**
	 * Cancellation prevents provider consumption and closes stream resources.
	 */
	public function test_pre_cancelled_stream_is_not_consumed_and_is_closed(): void {
		$cancellation = new Cancellation();
		$cancellation->cancel();
		$stream = $this->stream( array( 'must not be consumed' ) );

		$events = iterator_to_array(
			( new StreamingChatOrchestrator() )->stream( $stream, CitationRegistry::from_candidates( array() ), $cancellation ),
			false
		);

		self::assertSame( array( StreamEventType::MESSAGE_START, StreamEventType::ERROR ), array_map( static fn ( StreamEvent $event ): StreamEventType => $event->type, $events ) );
		self::assertSame( ChatFailureReason::CANCELLED, $events[1]->error_reason );
		self::assertSame( 0, $stream->next_calls );
		self::assertTrue( $stream->closed );
	}

	/**
	 * Unknown citations fail closed without a completion event.
	 */
	public function test_invalid_citation_is_terminal_error_without_completion(): void {
		$stream = $this->stream( array( 'Unsupported [C9]' ) );
		$events = iterator_to_array(
			( new StreamingChatOrchestrator() )->stream( $stream, $this->registry(), new Cancellation() ),
			false
		);

		self::assertSame( StreamEventType::ERROR, $events[ count( $events ) - 1 ]->type );
		self::assertSame( ChatFailureReason::INVALID_CITATIONS, $events[ count( $events ) - 1 ]->error_reason );
		self::assertNotContains( StreamEventType::MESSAGE_COMPLETE, array_map( static fn ( StreamEvent $event ): StreamEventType => $event->type, $events ) );
	}

	/**
	 * Build a trusted one-citation registry.
	 */
	private function registry(): CitationRegistry {
		return CitationRegistry::from_candidates(
			array(
				new RetrievalCandidate( 'chunk-1', 'doc-1', 1, 'Trusted evidence.', 'en', 'public', array(), 1.0 ),
			)
		);
	}

	/**
	 * Build a deterministic generation stream fake.
	 *
	 * @param array $deltas Deltas returned in order.
	 * @phpstan-param list<string> $deltas
	 */
	private function stream( array $deltas ): GenerationStream {
		return new class( $deltas ) implements GenerationStream {
			/**
			 * Remaining normalized deltas.
			 *
			 * @var list<string>
			 */
			private array $deltas;

			/**
			 * Number of attempted reads.
			 *
			 * @var int
			 */
			public int $next_calls = 0;

			/**
			 * Whether cleanup ran.
			 *
			 * @var bool
			 */
			public bool $closed = false;

			/**
			 * Create a deterministic stream fake.
			 *
			 * @param array $deltas Deltas returned in order.
			 * @phpstan-param list<string> $deltas
			 */
			public function __construct( array $deltas ) {
				$this->deltas = $deltas;
			}

			/**
			 * Read the next deterministic delta.
			 */
			public function next_delta(): ?string {
				++$this->next_calls;
				return array_shift( $this->deltas );
			}

			/**
			 * Record provider stream cleanup.
			 */
			public function close(): void {
				$this->closed = true;
			}
		};
	}

	/**
	 * Build a stream fake that throws a raw provider diagnostic.
	 */
	private function throwing_stream(): GenerationStream {
		return new class() implements GenerationStream {
			/**
			 * Whether cleanup ran.
			 *
			 * @var bool
			 */
			public bool $closed = false;

			/**
			 * Throw the deterministic raw provider failure.
			 */
			public function next_delta(): ?string {
				throw new RuntimeException( 'raw vendor diagnostic must not be exposed' );
			}

			/**
			 * Record provider stream cleanup.
			 */
			public function close(): void {
				$this->closed = true;
			}
		};
	}
}
