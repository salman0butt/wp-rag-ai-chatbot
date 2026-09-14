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

	/** Bot/date/transcript filters use prepared values without narrowing the aggregate message join. */
	public function test_applies_prepared_bot_date_and_transcript_filters(): void {
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )
			->method( 'prepare' )
			->with(
				self::callback(
					static fn ( string $sql ): bool => str_contains( $sql, 'WHERE c.bot_id = %s' )
						&& str_contains( $sql, 'c.created_at >= %s' )
						&& str_contains( $sql, 'c.created_at <= %s' )
						&& str_contains( $sql, 'EXISTS (' )
						&& str_contains( $sql, 'FROM %i AS sm' )
						&& str_contains( $sql, 'sm.conversation_id = c.conversation_id' )
						&& str_contains( $sql, 'sm.owner_scope = c.owner_scope' )
						&& str_contains( $sql, 'sm.content LIKE %s' )
				),
				'wp_rag_ai_conversations',
				'wp_rag_ai_messages',
				'bot-1',
				'2026-09-01 00:00:00',
				'2026-09-14 23:59:59',
				'wp_rag_ai_messages',
				'%50\\%\\_match%',
				25,
				0
			)
			->willReturn( 'filtered' );
		$connection->expects( self::once() )
			->method( 'get_results' )
			->with( 'filtered' )
			->willReturn( array() );

		$repository = new WpdbConversationReadRepository( $connection, new TableNames( 'wp_' ) );
		$query      = new ConversationListQuery(
			1,
			25,
			'bot-1',
			false,
			'2026-09-01 00:00:00',
			'2026-09-14 23:59:59',
			'50%_match'
		);

		self::assertSame( array(), $repository->list( $query ) );
	}

	/** Historical conversations are filtered with an explicit SQL NULL bucket, never a guessed bot identity. */
	public function test_applies_explicit_unassigned_filter(): void {
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )
			->method( 'prepare' )
			->with(
				self::callback(
					static fn ( string $sql ): bool => str_contains( $sql, 'WHERE c.bot_id IS NULL' )
						&& ! str_contains( $sql, 'c.bot_id = %s' )
				),
				'wp_rag_ai_conversations',
				'wp_rag_ai_messages',
				25,
				0
			)
			->willReturn( 'unassigned' );
		$connection->expects( self::once() )
			->method( 'get_results' )
			->with( 'unassigned' )
			->willReturn( array() );

		$repository = new WpdbConversationReadRepository( $connection, new TableNames( 'wp_' ) );

		self::assertSame( array(), $repository->list( new ConversationListQuery( 1, 25, null, true ) ) );
	}

	/** Detail reads keep owner scope internal and return a bounded chronological canonical transcript. */
	public function test_reads_one_canonical_conversation_with_bounded_chronological_transcript(): void {
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::exactly( 2 ) )
			->method( 'prepare' )
			->willReturnCallback(
				static function ( string $sql, mixed ...$args ): string {
					if ( str_contains( $sql, 'FROM %i AS c' ) ) {
						self::assertStringContainsString( 'WHERE c.conversation_id = %s', $sql );
						self::assertStringContainsString( 'LIMIT 1', $sql );
						self::assertSame( array( 'wp_rag_ai_conversations', 'conversation-1' ), $args );
						return 'conversation-detail';
					}

					self::assertStringContainsString( 'FROM %i AS m', $sql );
					self::assertStringContainsString( 'm.conversation_id = %s', $sql );
					self::assertStringContainsString( 'm.owner_scope = %s', $sql );
					self::assertStringContainsString( 'ORDER BY m.created_at ASC, m.id ASC', $sql );
					self::assertStringContainsString( 'LIMIT %d', $sql );
					self::assertSame( array( 'wp_rag_ai_messages', 'conversation-1', 'visitor:abc', 100 ), $args );
					return 'conversation-messages';
				}
			);
		$connection->expects( self::once() )
			->method( 'get_row' )
			->with( 'conversation-detail' )
			->willReturn(
				array(
					'conversation_id' => 'conversation-1',
					'bot_id'          => 'bot-1',
					'owner_scope'     => 'visitor:abc',
					'started_at'      => '2026-09-14 03:00:00',
				)
			);
		$connection->expects( self::once() )
			->method( 'get_results' )
			->with( 'conversation-messages' )
			->willReturn(
				array(
					array(
						'role'       => 'user',
						'content'    => 'Hello',
						'created_at' => '2026-09-14 03:00:01',
					),
					array(
						'role'       => 'assistant',
						'content'    => 'Hi there',
						'created_at' => '2026-09-14 03:00:02',
					),
				)
			);

		$repository = new WpdbConversationReadRepository( $connection, new TableNames( 'wp_' ) );
		self::assertTrue( method_exists( $repository, 'find' ), 'Conversation detail read boundary is missing.' );
		if ( ! method_exists( $repository, 'find' ) ) {
			return;
		}

		$detail = $repository->find( 'conversation-1', 1000 );

		self::assertNotNull( $detail );
		self::assertSame( 'conversation-1', $detail->conversation_id );
		self::assertSame( 'bot-1', $detail->bot_id );
		self::assertSame( '2026-09-14 03:00:00', $detail->started_at );
		self::assertCount( 2, $detail->messages );
		self::assertSame( 'user', $detail->messages[0]->role );
		self::assertSame( 'Hello', $detail->messages[0]->content );
		self::assertSame( 'assistant', $detail->messages[1]->role );
		self::assertFalse( property_exists( $detail, 'owner_scope' ) );
	}
}
