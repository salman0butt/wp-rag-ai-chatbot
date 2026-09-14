<?php
/**
 * Conversation administration mutation repository tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database\Repository;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Conversations\ConversationAdminRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbConversationAdminRepository;
use WpRagAiChatbot\Database\TableNames;

/** Specifies explicit administrator conversation deletion semantics. */
final class WpdbConversationAdminRepositoryTest extends TestCase {
	/** Deletion locks the canonical conversation and removes dependent messages before the conversation row. */
	public function test_deletes_canonical_conversation_and_dependent_messages_transactionally(): void {
		self::assertTrue( interface_exists( ConversationAdminRepository::class ), 'ConversationAdminRepository is missing.' );
		self::assertTrue( class_exists( WpdbConversationAdminRepository::class ), 'WpdbConversationAdminRepository is missing.' );
		if ( ! interface_exists( ConversationAdminRepository::class ) || ! class_exists( WpdbConversationAdminRepository::class ) ) {
			return;
		}

		$connection  = $this->createMock( Connection::class );
		$query_index = 0;
		$connection->expects( self::exactly( 2 ) )
			->method( 'query' )
			->willReturnCallback(
				static function ( string $sql ) use ( &$query_index ): int {
					self::assertSame( 0 === $query_index ? 'START TRANSACTION' : 'COMMIT', $sql );
					++$query_index;
					return 1;
				}
			);
		$connection->expects( self::once() )
			->method( 'prepare' )
			->with(
				self::callback(
					static fn ( string $sql ): bool => str_contains( $sql, 'FROM %i' )
						&& str_contains( $sql, 'WHERE conversation_id = %s' )
						&& str_contains( $sql, 'FOR UPDATE' )
				),
				'wp_rag_ai_conversations',
				'conversation-1'
			)
			->willReturn( 'locked-conversation' );
		$connection->expects( self::once() )
			->method( 'get_row' )
			->with( 'locked-conversation' )
			->willReturn( array( 'owner_scope' => 'visitor:abc' ) );

		$delete_index = 0;
		$connection->expects( self::exactly( 2 ) )
			->method( 'delete' )
			->willReturnCallback(
				static function ( string $table, array $where, array $formats ) use ( &$delete_index ): int {
					if ( 0 === $delete_index ) {
						self::assertSame( 'wp_rag_ai_messages', $table );
						self::assertSame(
							array(
								'conversation_id' => 'conversation-1',
								'owner_scope'     => 'visitor:abc',
							),
							$where
						);
						self::assertSame( array( '%s', '%s' ), $formats );
						++$delete_index;
						return 2;
					}

					self::assertSame( 'wp_rag_ai_conversations', $table );
					self::assertSame(
						array(
							'conversation_id' => 'conversation-1',
							'owner_scope'     => 'visitor:abc',
						),
						$where
					);
					self::assertSame( array( '%s', '%s' ), $formats );
					++$delete_index;
					return 1;
				}
			);

		$repository = new WpdbConversationAdminRepository( $connection, new TableNames( 'wp_' ) );
		self::assertInstanceOf( ConversationAdminRepository::class, $repository );
		self::assertTrue( $repository->delete( 'conversation-1' ) );
	}

	/** Missing conversations return false without issuing dependent deletes. */
	public function test_delete_returns_false_when_conversation_is_missing(): void {
		if ( ! class_exists( WpdbConversationAdminRepository::class ) ) {
			self::markTestSkipped( 'Deletion repository is specified by the preceding RED assertion.' );
		}

		$connection  = $this->createMock( Connection::class );
		$query_index = 0;
		$connection->expects( self::exactly( 2 ) )
			->method( 'query' )
			->willReturnCallback(
				static function ( string $sql ) use ( &$query_index ): int {
					self::assertSame( 0 === $query_index ? 'START TRANSACTION' : 'COMMIT', $sql );
					++$query_index;
					return 1;
				}
			);
		$connection->expects( self::once() )
			->method( 'prepare' )
			->willReturn( 'missing-conversation' );
		$connection->expects( self::once() )
			->method( 'get_row' )
			->with( 'missing-conversation' )
			->willReturn( null );
		$connection->expects( self::never() )->method( 'delete' );

		$repository = new WpdbConversationAdminRepository( $connection, new TableNames( 'wp_' ) );
		self::assertFalse( $repository->delete( 'missing-conversation' ) );
	}
}
