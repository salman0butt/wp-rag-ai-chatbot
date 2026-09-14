<?php
/**
 * Conversation administration transcript chronology regression tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database\Repository;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbConversationReadRepository;
use WpRagAiChatbot\Database\TableNames;

/** Protects the stable chronological ordering required for admin transcripts. */
final class WpdbConversationReadRepositoryChronologyTest extends TestCase {
	/** Transcript reads sort by persisted time first and message id as the deterministic tie-breaker. */
	public function test_detail_transcript_orders_by_created_at_then_id(): void {
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::exactly( 2 ) )
			->method( 'prepare' )
			->willReturnCallback(
				static function ( string $sql, mixed ...$args ): string {
					if ( str_contains( $sql, 'FROM %i AS c' ) ) {
						self::assertSame( array( 'wp_rag_ai_conversations', 'conversation-chronology' ), $args );
						return 'conversation-detail';
					}

					self::assertStringContainsString( 'FROM %i AS m', $sql );
					self::assertStringContainsString( 'ORDER BY m.created_at ASC, m.id ASC', $sql );
					return 'conversation-messages';
				}
			);
		$connection->expects( self::once() )
			->method( 'get_row' )
			->with( 'conversation-detail' )
			->willReturn(
				array(
					'conversation_id' => 'conversation-chronology',
					'bot_id'          => 'bot-1',
					'owner_scope'     => 'visitor:chronology',
					'started_at'      => '2026-09-14 05:00:00',
				)
			);
		$connection->expects( self::once() )
			->method( 'get_results' )
			->with( 'conversation-messages' )
			->willReturn( array() );

		$repository = new WpdbConversationReadRepository( $connection, new TableNames( 'wp_' ) );
		$detail     = $repository->find( 'conversation-chronology' );

		self::assertNotNull( $detail );
	}
}
