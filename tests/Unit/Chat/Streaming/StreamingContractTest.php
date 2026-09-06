<?php
/**
 * Streaming contract tests for M11 Task 8.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat\Streaming;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Specifies the provider-neutral streaming value and control contracts.
 */
final class StreamingContractTest extends TestCase {
	/**
	 * Required streaming types exist before orchestration can be implemented.
	 */
	public function test_streaming_contract_types_exist(): void {
		self::assertTrue( enum_exists( 'WpRagAiChatbot\\Chat\\Streaming\\StreamEventType' ), 'StreamEventType is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\Streaming\\StreamEvent' ), 'StreamEvent is missing.' );
		self::assertTrue( interface_exists( 'WpRagAiChatbot\\Chat\\Streaming\\GenerationStream' ), 'GenerationStream is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\Streaming\\Cancellation' ), 'Cancellation is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\Streaming\\StreamingChatOrchestrator' ), 'StreamingChatOrchestrator is missing.' );
	}

	/**
	 * Event type values are stable and deliberately exclude future tool/action events.
	 */
	public function test_stream_event_type_values_are_stable(): void {
		$type_class = 'WpRagAiChatbot\\Chat\\Streaming\\StreamEventType';

		self::assertTrue( enum_exists( $type_class ), 'StreamEventType is missing.' );

		$actual = array_map(
			static fn ( object $type ): string => $type->value,
			$type_class::cases()
		);

		self::assertSame(
			array( 'message.start', 'message.delta', 'citation', 'message.complete', 'error' ),
			$actual
		);
	}

	/**
	 * StreamEvent exposes only normalized bounded event fields.
	 */
	public function test_stream_event_constructor_is_bounded_contract(): void {
		$event_class = 'WpRagAiChatbot\\Chat\\Streaming\\StreamEvent';

		self::assertTrue( class_exists( $event_class ), 'StreamEvent is missing.' );

		$constructor = ( new ReflectionClass( $event_class ) )->getConstructor();
		self::assertNotNull( $constructor );
		self::assertSame( array( 'sequence', 'type', 'text', 'citation_id', 'error_reason' ), array_map( static fn ( $parameter ): string => $parameter->getName(), $constructor->getParameters() ) );
	}

	/**
	 * Cancellation is an application-owned mutable check hook.
	 */
	public function test_cancellation_can_be_requested_idempotently(): void {
		$class = 'WpRagAiChatbot\\Chat\\Streaming\\Cancellation';
		self::assertTrue( class_exists( $class ), 'Cancellation is missing.' );

		$cancellation = new $class();
		self::assertFalse( $cancellation->is_cancelled() );
		$cancellation->cancel();
		$cancellation->cancel();
		self::assertTrue( $cancellation->is_cancelled() );
	}
}
