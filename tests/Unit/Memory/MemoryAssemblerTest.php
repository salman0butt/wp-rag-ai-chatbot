<?php
/**
 * Conversation memory assembly tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Memory;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Conversations\ConversationMessage;

/**
 * Specifies deterministic, owner-scoped, bounded M11 memory assembly.
 */
final class MemoryAssemblerTest extends TestCase {
	/**
	 * Task 3 contracts must exist before later orchestration can consume memory.
	 */
	public function test_memory_contracts_exist(): void {
		self::assertTrue( interface_exists( 'WpRagAiChatbot\\Memory\\ConversationHistory' ), 'ConversationHistory contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Memory\\ConversationMemory' ), 'ConversationMemory contract is missing.' );
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Memory\\MemoryAssembler' ), 'MemoryAssembler is missing.' );
	}

	/**
	 * The assembler asks history for only the owner-scoped recent window.
	 */
	public function test_assembler_loads_owner_scoped_recent_history_with_hard_limit(): void {
		$history_class   = 'WpRagAiChatbot\\Memory\\ConversationHistory';
		$assembler_class = 'WpRagAiChatbot\\Memory\\MemoryAssembler';

		self::assertTrue( interface_exists( $history_class ), 'ConversationHistory contract is missing.' );
		self::assertTrue( class_exists( $assembler_class ), 'MemoryAssembler is missing.' );

		$history = $this->createMock( $history_class );
		$history
			->expects( self::once() )
			->method( 'recent_for_owner' )
			->with( 'conversation-1', 'owner-1', 12 )
			->willReturn( array( new ConversationMessage( 'user', 'Newest' ) ) );
		$history
			->expects( self::once() )
			->method( 'summary_for_owner' )
			->with( 'conversation-1', 'owner-1' )
			->willReturn( null );

		$memory = ( new ReflectionClass( $assembler_class ) )->newInstance( $history )->assemble( 'conversation-1', 'owner-1' );

		self::assertCount( 1, $memory->messages );
		self::assertSame( 'Newest', $memory->messages[0]->content );
	}

	/**
	 * If a source returns too much data, the newest twelve messages survive in chronological order.
	 */
	public function test_assembler_keeps_newest_twelve_messages_in_chronological_order(): void {
		$history_class   = 'WpRagAiChatbot\\Memory\\ConversationHistory';
		$assembler_class = 'WpRagAiChatbot\\Memory\\MemoryAssembler';

		self::assertTrue( interface_exists( $history_class ), 'ConversationHistory contract is missing.' );
		self::assertTrue( class_exists( $assembler_class ), 'MemoryAssembler is missing.' );

		$messages = array();
		for ( $index = 1; $index <= 15; ++$index ) {
			$messages[] = new ConversationMessage( 'user', 'message-' . $index );
		}

		$history = $this->createStub( $history_class );
		$history->method( 'recent_for_owner' )->willReturn( $messages );
		$history->method( 'summary_for_owner' )->willReturn( null );

		$memory = ( new ReflectionClass( $assembler_class ) )->newInstance( $history )->assemble( 'conversation-1', 'owner-1' );
		$actual = array_map(
			static fn ( ConversationMessage $message ): string => $message->content,
			$memory->messages
		);

		self::assertCount( 12, $memory->messages );
		self::assertSame( 'message-4', $actual[0] );
		self::assertSame( 'message-15', $actual[11] );
	}

	/**
	 * Oldest message content is discarded first until the total memory text fits 24 KiB.
	 */
	public function test_assembler_drops_oldest_messages_to_fit_memory_byte_budget(): void {
		$history_class   = 'WpRagAiChatbot\\Memory\\ConversationHistory';
		$assembler_class = 'WpRagAiChatbot\\Memory\\MemoryAssembler';

		self::assertTrue( interface_exists( $history_class ), 'ConversationHistory contract is missing.' );
		self::assertTrue( class_exists( $assembler_class ), 'MemoryAssembler is missing.' );

		$history = $this->createStub( $history_class );
		$history->method( 'recent_for_owner' )->willReturn(
			array(
				new ConversationMessage( 'user', str_repeat( 'a', 9000 ) ),
				new ConversationMessage( 'assistant', str_repeat( 'b', 9000 ) ),
				new ConversationMessage( 'user', str_repeat( 'c', 9000 ) ),
			)
		);
		$history->method( 'summary_for_owner' )->willReturn( null );

		$memory = ( new ReflectionClass( $assembler_class ) )->newInstance( $history )->assemble( 'conversation-1', 'owner-1' );
		$bytes  = array_sum(
			array_map(
				static fn ( ConversationMessage $message ): int => strlen( $message->content ),
				$memory->messages
			)
		);

		self::assertLessThanOrEqual( 24576, $bytes );
		self::assertCount( 2, $memory->messages );
		self::assertSame( str_repeat( 'b', 9000 ), $memory->messages[0]->content );
		self::assertSame( str_repeat( 'c', 9000 ), $memory->messages[1]->content );
	}

	/**
	 * A single owner-scoped versioned summary is preserved when it fits the memory budget.
	 */
	public function test_assembler_includes_one_versioned_summary(): void {
		$history_class   = 'WpRagAiChatbot\\Memory\\ConversationHistory';
		$assembler_class = 'WpRagAiChatbot\\Memory\\MemoryAssembler';

		self::assertTrue( interface_exists( $history_class ), 'ConversationHistory contract is missing.' );
		self::assertTrue( class_exists( $assembler_class ), 'MemoryAssembler is missing.' );

		$history = $this->createStub( $history_class );
		$history->method( 'recent_for_owner' )->willReturn( array( new ConversationMessage( 'user', 'Current turn' ) ) );
		$history->method( 'summary_for_owner' )->willReturn(
			array(
				'version' => 2,
				'text'    => 'Earlier conversation summary.',
			)
		);

		$memory = ( new ReflectionClass( $assembler_class ) )->newInstance( $history )->assemble( 'conversation-1', 'owner-1' );

		self::assertSame( 2, $memory->summary_version );
		self::assertSame( 'Earlier conversation summary.', $memory->summary_text );
	}
}