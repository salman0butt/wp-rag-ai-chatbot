<?php
/**
 * Streaming total-output bound regression for M11 Task 8.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat\Streaming;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Chat\ChatFailureReason;
use WpRagAiChatbot\Chat\Streaming\Cancellation;
use WpRagAiChatbot\Chat\Streaming\GenerationStream;
use WpRagAiChatbot\Chat\Streaming\StreamEvent;
use WpRagAiChatbot\Chat\Streaming\StreamEventType;
use WpRagAiChatbot\Chat\Streaming\StreamingChatOrchestrator;
use WpRagAiChatbot\Citations\CitationRegistry;

/**
 * Proves streamed output cannot exceed the persisted-message safety ceiling.
 */
final class StreamingOutputBoundTest extends TestCase {
	private const MAX_OUTPUT_BYTES = 65536;

	/**
	 * A provider that exceeds the application output ceiling must fail closed.
	 */
	public function test_stream_stops_before_accepting_output_beyond_hard_limit(): void {
		$stream = new class() implements GenerationStream {
			public int $next_calls = 0;
			public bool $closed = false;

			public function next_delta(): ?string {
				++$this->next_calls;
				if ( $this->next_calls > 17 ) {
					return null;
				}

				return str_repeat( 'a', 4096 );
			}

			public function close(): void {
				$this->closed = true;
			}
		};

		$events = iterator_to_array(
			( new StreamingChatOrchestrator() )->stream(
				$stream,
				CitationRegistry::from_candidates( array() ),
				new Cancellation()
			),
			false
		);

		$deltas = array_values(
			array_filter(
				$events,
				static fn ( StreamEvent $event ): bool => StreamEventType::MESSAGE_DELTA === $event->type
			)
		);
		$total_bytes = array_sum(
			array_map(
				static fn ( StreamEvent $event ): int => strlen( (string) $event->text ),
				$deltas
			)
		);

		self::assertSame( self::MAX_OUTPUT_BYTES, $total_bytes );
		self::assertSame( StreamEventType::ERROR, $events[ count( $events ) - 1 ]->type );
		self::assertSame( ChatFailureReason::GENERATION_FAILED, $events[ count( $events ) - 1 ]->error_reason );
		self::assertNotContains(
			StreamEventType::MESSAGE_COMPLETE,
			array_map( static fn ( StreamEvent $event ): StreamEventType => $event->type, $events )
		);
		self::assertSame( 17, $stream->next_calls );
		self::assertTrue( $stream->closed );
	}
}
