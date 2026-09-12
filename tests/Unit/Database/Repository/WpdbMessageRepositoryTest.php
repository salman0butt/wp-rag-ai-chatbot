<?php
/**
 * WordPress conversation message repository tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database\Repository;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbMessageRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Memory\ConversationHistory;

/** Specifies owner-scoped message persistence and memory reads. */
final class WpdbMessageRepositoryTest extends TestCase {
	/** The production message repository is also the canonical conversation-history authority. */
	public function test_implements_conversation_history(): void {
		$repository = new WpdbMessageRepository(
			$this->createMock( Connection::class ),
			new TableNames( 'wp_' )
		);

		self::assertInstanceOf( ConversationHistory::class, $repository );
	}

	/** Recent memory reads are owner-scoped, bounded, and restored to chronological order. */
	public function test_reads_recent_messages_for_owner(): void {
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )
			->method( 'prepare' )
			->with(
				self::stringContains( 'ORDER BY id DESC LIMIT %d' ),
				'wp_rag_ai_messages',
				'conversation-1',
				'public:visitor-1',
				2
			)
			->willReturn( 'prepared' );
		$connection->expects( self::once() )
			->method( 'get_results' )
			->with( 'prepared' )
			->willReturn(
				array(
					array( 'role' => 'assistant', 'content' => 'Second' ),
					array( 'role' => 'user', 'content' => 'First' ),
				)
			);

		$messages = ( new WpdbMessageRepository( $connection, new TableNames( 'wp_' ) ) )->recent_for_owner(
			'conversation-1',
			'public:visitor-1',
			2
		);

		self::assertSame( array( 'user', 'assistant' ), array_map( static fn ( $message ): string => $message->role, $messages ) );
		self::assertSame( array( 'First', 'Second' ), array_map( static fn ( $message ): string => $message->content, $messages ) );
	}

	/** Summary memory remains absent until a dedicated persisted summary authority exists. */
	public function test_summary_is_absent_without_summary_storage(): void {
		$repository = new WpdbMessageRepository(
			$this->createMock( Connection::class ),
			new TableNames( 'wp_' )
		);

		self::assertNull( $repository->summary_for_owner( 'conversation-1', 'public:visitor-1' ) );
	}
}
