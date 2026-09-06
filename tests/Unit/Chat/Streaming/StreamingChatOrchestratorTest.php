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
		self::assertTrue( $stream->closed ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double state.
	}

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
		self::assertTrue( $stream->closed ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double state.
	}

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
		self::assertSame( 0, $stream->next_calls ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double call count.
		self::assertTrue( $stream->closed ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Test double state.
	}

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

	private function registry(): CitationRegistry {
		return CitationRegistry::from_candidates(
			array(
				new RetrievalCandidate( 'chunk-1', 'doc-1', 1, 'Trusted evidence.', 'en', 'public', array(), 1.0 ),
			)
		);
	}

	/** @param list<string> $deltas Deltas returned in order. */
	private function stream( array $deltas ): GenerationStream {
		return new class( $deltas ) implements GenerationStream {
			/** @var list<string> */
			private array $deltas;
			public int $next_calls = 0;
			public bool $closed = false;

			/** @param list<string> $deltas Deltas. */
			public function __construct( array $deltas ) {
				$this->deltas = $deltas;
			}

			public function next_delta(): ?string {
				++$this->next_calls;
				return array_shift( $this->deltas );
			}

			public function close(): void {
				$this->closed = true;
			}
		};
	}

	private function throwing_stream(): GenerationStream {
		return new class() implements GenerationStream {
			public bool $closed = false;

			public function next_delta(): ?string {
				throw new RuntimeException( 'raw vendor diagnostic must not be exposed' );
			}

			public function close(): void {
				$this->closed = true;
			}
		};
	}
}
