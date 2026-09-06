<?php
/**
 * Ownership-scoped conversation persistence contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Conversations;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseSchema;

/**
 * Defines the minimum durable M11 conversation-persistence contract.
 */
final class ConversationPersistenceContractTest extends TestCase {
	/**
	 * M11 persistence advances the additive schema through V009.
	 */
	public function test_conversation_migrations_advance_schema_through_v009(): void {
		self::assertTrue(
			class_exists( 'WpRagAiChatbot\\Database\\Migrations\\V007CreateConversationsTable' ),
			'V007CreateConversationsTable must exist before M11 Task 2 can pass.'
		);
		self::assertTrue(
			class_exists( 'WpRagAiChatbot\\Database\\Migrations\\V008CreateMessagesTable' ),
			'V008CreateMessagesTable must exist before M11 Task 2 can pass.'
		);
		self::assertTrue(
			class_exists( 'WpRagAiChatbot\\Database\\Migrations\\V009CreateMessageCitationsTable' ),
			'V009CreateMessageCitationsTable must exist before M11 Task 2 can pass.'
		);
		self::assertSame( 9, DatabaseSchema::VERSION );
	}

	/**
	 * Every conversation lookup and creation operation requires owner scope.
	 */
	public function test_conversation_repository_contract_requires_owner_scope(): void {
		$repository = 'WpRagAiChatbot\\Conversations\\ConversationRepository';
		self::assertTrue( interface_exists( $repository ), 'ConversationRepository must exist before M11 Task 2 can pass.' );
		self::assertTrue( method_exists( $repository, 'find_for_owner' ) );
		self::assertTrue( method_exists( $repository, 'create_for_owner' ) );
	}

	/**
	 * Every message append operation requires both conversation and owner scope.
	 */
	public function test_message_repository_contract_requires_owner_scope(): void {
		$repository = 'WpRagAiChatbot\\Conversations\\MessageRepository';
		self::assertTrue( interface_exists( $repository ), 'MessageRepository must exist before M11 Task 2 can pass.' );
		self::assertTrue( method_exists( $repository, 'append_for_owner' ) );
	}
}
