<?php
/**
 * M16 conversation bot-association migration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\Migration;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/** Defines schema version fourteen for explicit nullable conversation bot association. */
final class ConversationBotAssociationMigrationContractTest extends TestCase {
	/** V014 adds nullable bot identity while preserving historical unassigned conversation rows. */
	public function test_conversation_bot_association_is_part_of_schema_version_fourteen(): void {
		$migration_class = 'WpRagAiChatbot\\Database\\Migrations\\V014AddConversationBotAssociation';

		self::assertSame( 14, DatabaseSchema::VERSION );
		self::assertTrue(
			class_exists( $migration_class ),
			'M16 Task 1A requires V014AddConversationBotAssociation.'
		);

		$migration = new $migration_class( new TableNames( 'wp_' ) );
		self::assertInstanceOf( Migration::class, $migration );
		self::assertSame( 14, $migration->version() );

		$connection = new RecordingConnection();
		$migration->up( $connection );
		self::assertCount( 1, $connection->db_delta_queries );

		$sql = $connection->db_delta_queries[0];
		self::assertStringContainsString( 'conversation_id varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'owner_scope varchar(191) NOT NULL', $sql );
		self::assertStringContainsString( 'bot_id varchar(32) NULL', $sql );
		self::assertStringContainsString( 'KEY bot_updated (bot_id,updated_at)', $sql );
	}
}
