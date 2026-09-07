<?php
/**
 * Chat request contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Chat;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Specifies the first bounded M11 chat request/value-object contract.
 */
final class ChatRequestContractTest extends TestCase {
	/**
	 * The M11 request and grounding contracts must exist before orchestration can consume them.
	 */
	public function test_chat_request_and_grounding_contracts_exist(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Chat\\ChatRequest' ), 'ChatRequest contract is missing.' );
		self::assertTrue( enum_exists( 'WpRagAiChatbot\\RAG\\GroundingMode' ), 'GroundingMode contract is missing.' );
	}

	/**
	 * Requests reject input larger than the application safety ceiling.
	 */
	public function test_request_rejects_question_over_sixteen_kib(): void {
		$request_class = 'WpRagAiChatbot\\Chat\\ChatRequest';
		$mode_class    = 'WpRagAiChatbot\\RAG\\GroundingMode';

		self::assertTrue( class_exists( $request_class ), 'ChatRequest contract is missing.' );
		self::assertTrue( enum_exists( $mode_class ), 'GroundingMode contract is missing.' );

		$request = new ReflectionClass( $request_class );
		$mode    = constant( $mode_class . '::STRICT' );

		$this->expectException( InvalidArgumentException::class );
		$request->newInstance( str_repeat( 'a', 16385 ), 'test-model', $mode );
	}

	/**
	 * Caller-selected model identifiers are hard-bounded before provider dispatch.
	 */
	public function test_request_rejects_oversized_model_identifier(): void {
		$request_class = 'WpRagAiChatbot\\Chat\\ChatRequest';
		$mode_class    = 'WpRagAiChatbot\\RAG\\GroundingMode';

		$request = new ReflectionClass( $request_class );
		$mode    = constant( $mode_class . '::STRICT' );

		$this->expectException( InvalidArgumentException::class );
		$request->newInstance( 'Question?', str_repeat( 'm', 256 ), $mode );
	}

	/**
	 * Caller-supplied conversation identifiers are bounded before repository use.
	 */
	public function test_request_rejects_oversized_conversation_identifier(): void {
		$request_class = 'WpRagAiChatbot\\Chat\\ChatRequest';
		$mode_class    = 'WpRagAiChatbot\\RAG\\GroundingMode';

		$request = new ReflectionClass( $request_class );
		$mode    = constant( $mode_class . '::STRICT' );

		$this->expectException( InvalidArgumentException::class );
		$request->newInstance( 'Question?', 'test-model', $mode, str_repeat( 'c', 256 ) );
	}

	/**
	 * A valid request preserves normalized application inputs and the default output ceiling.
	 */
	public function test_valid_request_preserves_normalized_inputs(): void {
		$request_class = 'WpRagAiChatbot\\Chat\\ChatRequest';
		$mode_class    = 'WpRagAiChatbot\\RAG\\GroundingMode';

		self::assertTrue( class_exists( $request_class ), 'ChatRequest contract is missing.' );
		self::assertTrue( enum_exists( $mode_class ), 'GroundingMode contract is missing.' );

		$request = ( new ReflectionClass( $request_class ) )->newInstance(
			'  How do I reset the device?  ',
			'  test-model  ',
			constant( $mode_class . '::STRICT' ),
			' conversation-123 '
		);

		self::assertSame( 'How do I reset the device?', $request->question );
		self::assertSame( 'test-model', $request->model_id );
		self::assertSame( 'conversation-123', $request->conversation_id );
		self::assertSame( 4096, $request->max_output_tokens );
	}
}
