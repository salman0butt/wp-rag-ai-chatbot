<?php
/**
 * Chat result contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Providers\Usage;

/**
 * Specifies the normalized M11 chat result and failure-reason contracts.
 */
final class ChatResultContractTest extends TestCase {
	/**
	 * M11 exposes normalized result and stable failure-reason contracts.
	 */
	public function test_chat_result_and_failure_reason_contracts_exist(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\ChatResult' ), 'ChatResult contract is missing.' );
		self::assertTrue( enum_exists( 'WpRagAiChatbot\\Chat\\ChatFailureReason' ), 'ChatFailureReason contract is missing.' );
	}

	/**
	 * A normalized successful result preserves only application-level response fields.
	 */
	public function test_successful_result_preserves_normalized_fields(): void {
		$result_class = 'WpRagAiChatbot\\Chat\\ChatResult';

		self::assertTrue( class_exists( $result_class ), 'ChatResult contract is missing.' );

		$result = ( new ReflectionClass( $result_class ) )->newInstance(
			'  Reset the device from Settings.  ',
			false,
			new Usage( 10, 8, 18 ),
			array(),
			' conversation-123 ',
			' message-456 '
		);

		self::assertSame( 'Reset the device from Settings.', $result->answer );
		self::assertFalse( $result->no_answer );
		self::assertSame( 'conversation-123', $result->conversation_id );
		self::assertSame( 'message-456', $result->message_id );
		self::assertSame( 18, $result->usage?->total_tokens );
	}

	/**
	 * Stable failure reasons include the application categories required by M11.
	 */
	public function test_failure_reason_values_are_stable(): void {
		$reason_class = 'WpRagAiChatbot\\Chat\\ChatFailureReason';

		self::assertTrue( enum_exists( $reason_class ), 'ChatFailureReason contract is missing.' );

		$expected = array(
			'invalid_request',
			'conversation_not_found',
			'conversation_forbidden',
			'rate_limited',
			'budget_exceeded',
			'retrieval_unavailable',
			'insufficient_evidence',
			'generation_unavailable',
			'generation_failed',
			'invalid_citations',
			'persistence_failed',
			'cancelled',
		);
		$actual   = array_map(
			static fn ( object $reason ): string => $reason->value,
			$reason_class::cases()
		);

		self::assertSame( $expected, $actual );
	}
}
