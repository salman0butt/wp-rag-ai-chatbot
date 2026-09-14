<?php
/**
 * M16 conversation bot-association read tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Conversations;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Repository\WpdbConversationRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/** Proves explicit bot identity is readable while historical unassigned rows remain valid. */
final class ConversationBotAssociationReadTest extends TestCase {
	/** Assigned conversations expose their persisted bot identity. */
	public function test_assigned_conversation_hydrates_bot_identity(): void {
		$connection                 = new RecordingConnection();
		$connection->get_row_result = array(
			'conversation_id' => 'conversation-1',
			'owner_scope'     => 'owner-a',
			'bot_id'          => '0123456789abcdef0123456789abcdef',
		);
		$repository                 = new WpdbConversationRepository( $connection, new TableNames( 'wp_' ) );

		$conversation = $repository->find_for_owner( 'conversation-1', 'owner-a' );

		self::assertNotNull( $conversation );
		$properties = get_object_vars( $conversation );
		self::assertArrayHasKey( 'bot_id', $properties );
		self::assertSame( '0123456789abcdef0123456789abcdef', $properties['bot_id'] );
		self::assertStringContainsString( 'bot_id', $connection->prepared_calls[0]['query'] );
	}

	/** Historical conversations without a bot assignment hydrate with an explicit null association. */
	public function test_historical_unassigned_conversation_hydrates_null_bot_identity(): void {
		$connection                 = new RecordingConnection();
		$connection->get_row_result = array(
			'conversation_id' => 'conversation-legacy',
			'owner_scope'     => 'owner-a',
			'bot_id'          => null,
		);
		$repository                 = new WpdbConversationRepository( $connection, new TableNames( 'wp_' ) );

		$conversation = $repository->find_for_owner( 'conversation-legacy', 'owner-a' );

		self::assertNotNull( $conversation );
		$properties = get_object_vars( $conversation );
		self::assertArrayHasKey( 'bot_id', $properties );
		self::assertNull( $properties['bot_id'] );
	}
}
