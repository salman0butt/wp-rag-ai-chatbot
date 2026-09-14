<?php
/**
 * Conversation administration read repository tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database\Repository;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Conversations\ConversationListQuery;
use WpRagAiChatbot\Conversations\ConversationReadRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbConversationReadRepository;
use WpRagAiChatbot\Database\TableNames;

/** Specifies bounded canonical conversation administration reads. */
final class WpdbConversationReadRepositoryTest extends TestCase {
	/** List reads aggregate canonical messages and apply stable recency pagination. */
	public function test_lists_canonical_conversation_summaries_with_stable_pagination(): void {
		self::assertTrue( class_exists( WpdbConversationReadRepository::class ), 'WpdbConversationReadRepository is missing.' );
		if ( ! class_exists( WpdbConversationReadRepository::class ) ) {
			return;
		}

		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )
			->method( 'prepare' )
			->with(
				self::callback(
					static fn ( string $sql ): bool => str_contains( $sql, 'LEFT JOIN %i AS m' )
						&& str_contains( $sql, 'COUNT(m.id) AS message_count' )
						&& str_contains( $sql, 'ORDER BY COALESCE(MAX(m.created_at), c.created_at) DESC, c.id DESC' )
						&& str_contains( $sql, 'LIMIT %d OFFSET %d' )
				),
				'wp_rag_ai_conversations',
				'wp_rag_ai_messages',
				25,
				25
			)
			->willReturn( 'prepared' );
		$connection->expects( self::once() )
			->method( 'get_results' )
			->with( 'prepared' )
			->willReturn(
				array(
					array(
						'conversation_id'   => 'conversation-2',
						'bot_id'            => 'bot-2',
						'started_at'        => '2026-09-14 02:00:00',
						'latest_message_at' => '2026-09-14 02:05:00',
						'message_count'     => '3',
					),
					array(
						'conversation_id'   => 'conversation-legacy',
						'bot_id'            => null,
						'started_at'        => '2026-09-13 22:00:00',
						'latest_message_at' => null,
						'message_count'     => '0',
					),
				)
			);

		$repository = new WpdbConversationReadRepository( $connection, new TableNames( 'wp_' ) );
		self::assertInstanceOf( ConversationReadRepository::class, $repository );

		$rows = $repository->list( new ConversationListQuery( 2, 25 ) );

		self::assertCount( 2, $rows );
		self::assertSame( 'conversation-2', $rows[0]->conversation_id );
		self::assertSame( 'bot-2', $rows[0]->bot_id );
		self::assertSame( '2026-09-14 02:00:00', $rows[0]->started_at );
		self::assertSame( '2026-09-14 02:05:00', $rows[0]->latest_message_at );
		self::assertSame( 3, $rows[0]->message_count );
		self::assertNull( $rows[1]->bot_id );
		self::assertNull( $rows[1]->latest_message_at );
		self::assertSame( 0, $rows[1]->message_count );
	}
}
