<?php
/**
 * WordPress conversation repository adapter tests.
 *
 * @package WpRagAiChatbot
 */
declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Conversations;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Conversations\ConversationMessage;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\Repository\WpdbConversationRepository;
use WpRagAiChatbot\Database\Repository\WpdbMessageRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Proves owner scope is enforced at the SQL adapter boundary.
 */
final class WpdbConversationRepositoriesTest extends TestCase {
	/** Owner-scoped lookup uses both identifiers as prepared values. */
	public function test_conversation_lookup_is_prepared_and_owner_scoped(): void {
		$connection                 = new RecordingConnection();
		$connection->get_row_result = array(
			'conversation_id' => 'conversation-1',
			'owner_scope'     => 'owner-a',
		);
		$repository                 = new WpdbConversationRepository( $connection, new TableNames( 'wp_' ) );

		$conversation = $repository->find_for_owner( 'conversation-1', 'owner-a' );

		self::assertNotNull( $conversation );
		self::assertSame( 'conversation-1', $conversation->conversation_id );
		self::assertSame( 'owner-a', $conversation->owner_scope );
		self::assertCount( 1, $connection->prepared_calls );
		self::assertStringContainsString( 'conversation_id = %s AND owner_scope = %s', $connection->prepared_calls[0]['query'] );
		self::assertSame(
			array( 'wp_rag_ai_conversations', 'conversation-1', 'owner-a' ),
			$connection->prepared_calls[0]['args']
		);
	}

	/** Cross-owner lookup is indistinguishable from a missing conversation. */
	public function test_conversation_lookup_denies_cross_owner_rows(): void {
		$connection = new RecordingConnection();
		$repository = new WpdbConversationRepository( $connection, new TableNames( 'wp_' ) );

		self::assertNull( $repository->find_for_owner( 'conversation-1', 'owner-b' ) );
		self::assertSame(
			array( 'wp_rag_ai_conversations', 'conversation-1', 'owner-b' ),
			$connection->prepared_calls[0]['args']
		);
	}

	/** SQL-like identifier content remains a bound value instead of changing query structure. */
	public function test_malicious_looking_identifiers_remain_prepared_values(): void {
		$conversation_id            = "conversation' OR 1=1 --";
		$owner_scope                = "owner' OR 1=1 --";
		$connection                 = new RecordingConnection();
		$connection->get_row_result = array(
			'conversation_id' => $conversation_id,
			'owner_scope'     => $owner_scope,
		);
		$repository                 = new WpdbConversationRepository( $connection, new TableNames( 'wp_' ) );

		$conversation = $repository->find_for_owner( $conversation_id, $owner_scope );

		self::assertNotNull( $conversation );
		self::assertSame( $conversation_id, $conversation->conversation_id );
		self::assertSame( $owner_scope, $conversation->owner_scope );
		self::assertSame(
			array( 'wp_rag_ai_conversations', $conversation_id, $owner_scope ),
			$connection->prepared_calls[0]['args']
		);
		self::assertStringNotContainsString( $conversation_id, $connection->prepared_calls[0]['query'] );
	}

	/** Conversation creation persists only a bounded normalized owner scope. */
	public function test_conversation_creation_normalizes_and_bounds_owner_scope(): void {
		$connection = new RecordingConnection();
		$repository = new WpdbConversationRepository( $connection, new TableNames( 'wp_' ) );

		$conversation = $repository->create_for_owner( '  owner-a  ' );

		self::assertSame( 'owner-a', $conversation->owner_scope );
		self::assertSame( 'owner-a', $connection->insert_calls[0]['data']['owner_scope'] );
		self::assertNotSame( '', $conversation->conversation_id );
		self::assertLessThanOrEqual( 191, strlen( $conversation->conversation_id ) );

		$this->expectException( InvalidArgumentException::class );
		$repository->create_for_owner( str_repeat( 'o', 192 ) );
	}

	/** Message append is one atomic prepared insert-select scoped by conversation and owner. */
	public function test_message_append_is_atomic_prepared_and_owner_scoped(): void {
		$connection               = new RecordingConnection();
		$connection->query_result = 1;
		$repository               = new WpdbMessageRepository( $connection, new TableNames( 'wp_' ) );

		$repository->append_for_owner(
			'conversation-1',
			'owner-a',
			new ConversationMessage( 'user', 'Hello' )
		);

		self::assertCount( 1, $connection->prepared_calls );
		self::assertCount( 1, $connection->queries );
		self::assertStringContainsString( 'INSERT INTO %i', $connection->prepared_calls[0]['query'] );
		self::assertStringContainsString( 'WHERE conversation_id = %s AND owner_scope = %s', $connection->prepared_calls[0]['query'] );
		self::assertSame( 'wp_rag_ai_messages', $connection->prepared_calls[0]['args'][0] );
		self::assertSame( 'wp_rag_ai_conversations', $connection->prepared_calls[0]['args'][5] );
		self::assertSame( 'conversation-1', $connection->prepared_calls[0]['args'][6] );
		self::assertSame( 'owner-a', $connection->prepared_calls[0]['args'][7] );
	}

	/** Failed owner predicate rejects append without exposing whether the conversation exists. */
	public function test_message_append_denies_cross_owner_write(): void {
		$connection               = new RecordingConnection();
		$connection->query_result = 0;
		$repository               = new WpdbMessageRepository( $connection, new TableNames( 'wp_' ) );

		$this->expectException( DatabaseException::class );
		$this->expectExceptionMessage( 'Conversation is unavailable for message append.' );
		$repository->append_for_owner(
			'conversation-1',
			'owner-b',
			new ConversationMessage( 'user', 'Hello' )
		);
	}

	/** Persisted conversation, owner, role, and content fields all have hard byte ceilings. */
	public function test_message_append_rejects_oversized_persisted_fields_before_query(): void {
		$connection = new RecordingConnection();
		$repository = new WpdbMessageRepository( $connection, new TableNames( 'wp_' ) );

		try {
			$repository->append_for_owner(
				str_repeat( 'c', 192 ),
				'owner-a',
				new ConversationMessage( 'user', 'Hello' )
			);
			self::fail( 'Oversized conversation identifier should be rejected.' );
		} catch ( InvalidArgumentException ) {
			self::assertSame( array(), $connection->queries );
		}

		try {
			$repository->append_for_owner(
				'conversation-1',
				str_repeat( 'o', 192 ),
				new ConversationMessage( 'user', 'Hello' )
			);
			self::fail( 'Oversized owner scope should be rejected.' );
		} catch ( InvalidArgumentException ) {
			self::assertSame( array(), $connection->queries );
		}

		try {
			$repository->append_for_owner(
				'conversation-1',
				'owner-a',
				new ConversationMessage( str_repeat( 'r', 33 ), 'Hello' )
			);
			self::fail( 'Oversized role should be rejected.' );
		} catch ( InvalidArgumentException ) {
			self::assertSame( array(), $connection->queries );
		}

		try {
			$repository->append_for_owner(
				'conversation-1',
				'owner-a',
				new ConversationMessage( 'user', str_repeat( 'x', 65537 ) )
			);
			self::fail( 'Oversized message content should be rejected.' );
		} catch ( InvalidArgumentException ) {
			self::assertSame( array(), $connection->queries );
		}
	}
}