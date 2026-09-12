<?php
/**
 * Public chat request contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Specifies the closed public widget chat-request boundary.
 */
final class PublicChatRequestTest extends TestCase {
	/**
	 * The public request contract must exist before public runtime wiring can consume it.
	 */
	public function test_public_chat_request_contract_exists(): void {
		self::assertTrue(
			class_exists( 'WpRagAiChatbot\\Frontend\\PublicChatRequest' ),
			'PublicChatRequest contract is missing.'
		);
	}

	/**
	 * Valid public input is normalized without exposing runtime authority.
	 */
	public function test_valid_public_input_is_normalized(): void {
		$class = 'WpRagAiChatbot\\Frontend\\PublicChatRequest';
		self::assertTrue( class_exists( $class ), 'PublicChatRequest contract is missing.' );

		$reflection = new ReflectionClass( $class );
		$request    = $reflection->getMethod( 'from_array' )->invoke(
			null,
			array(
				'bot_id'          => '0123456789abcdef0123456789abcdef',
				'question'        => '  How do I reset the device?  ',
				'conversation_id' => ' conversation-123 ',
			)
		);

		self::assertSame(
			'0123456789abcdef0123456789abcdef',
			$reflection->getProperty( 'bot_id' )->getValue( $request )
		);
		self::assertSame(
			'How do I reset the device?',
			$reflection->getProperty( 'question' )->getValue( $request )
		);
		self::assertSame(
			'conversation-123',
			$reflection->getProperty( 'conversation_id' )->getValue( $request )
		);
	}

	/**
	 * Public callers cannot smuggle provider or retrieval authority through unknown keys.
	 */
	public function test_unknown_runtime_override_key_is_rejected(): void {
		$class = 'WpRagAiChatbot\\Frontend\\PublicChatRequest';
		self::assertTrue( class_exists( $class ), 'PublicChatRequest contract is missing.' );

		$this->expectException( InvalidArgumentException::class );
		( new ReflectionClass( $class ) )->getMethod( 'from_array' )->invoke(
			null,
			array(
				'bot_id'   => '0123456789abcdef0123456789abcdef',
				'question' => 'Question?',
				'model_id' => 'caller-selected-model',
			)
		);
	}

	/**
	 * Public questions remain bounded before any retrieval or paid generation work.
	 */
	public function test_oversized_question_is_rejected(): void {
		$class = 'WpRagAiChatbot\\Frontend\\PublicChatRequest';
		self::assertTrue( class_exists( $class ), 'PublicChatRequest contract is missing.' );

		$this->expectException( InvalidArgumentException::class );
		( new ReflectionClass( $class ) )->getMethod( 'from_array' )->invoke(
			null,
			array(
				'bot_id'   => '0123456789abcdef0123456789abcdef',
				'question' => str_repeat( 'q', 16385 ),
			)
		);
	}

	/**
	 * Conversation identifiers are optional but bounded when supplied.
	 */
	public function test_oversized_conversation_identifier_is_rejected(): void {
		$class = 'WpRagAiChatbot\\Frontend\\PublicChatRequest';
		self::assertTrue( class_exists( $class ), 'PublicChatRequest contract is missing.' );

		$this->expectException( InvalidArgumentException::class );
		( new ReflectionClass( $class ) )->getMethod( 'from_array' )->invoke(
			null,
			array(
				'bot_id'          => '0123456789abcdef0123456789abcdef',
				'question'        => 'Question?',
				'conversation_id' => str_repeat( 'c', 256 ),
			)
		);
	}
}
