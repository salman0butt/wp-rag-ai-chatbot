<?php
/**
 * M14 bot retrieval binding migration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\Migrations\V012AddBotRetrievalBinding;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/**
 * Defines the schema identity for server-owned bot retrieval bindings.
 */
final class BotRetrievalBindingMigrationContractTest extends TestCase {
	/** Task 4C advances the schema and preserves all existing bot fields. */
	public function test_bot_retrieval_binding_is_part_of_schema_version_twelve(): void {
		self::assertTrue(
			class_exists( V012AddBotRetrievalBinding::class ),
			'M14 Task 4C requires V012AddBotRetrievalBinding.'
		);
		self::assertSame( 12, DatabaseSchema::VERSION );

		$connection = new RecordingConnection();
		$migration  = new V012AddBotRetrievalBinding( new TableNames( 'wp_' ) );

		self::assertSame( 12, $migration->version() );
		$migration->up( $connection );
		self::assertCount( 1, $connection->db_delta_queries );

		$sql = $connection->db_delta_queries[0];
		self::assertStringContainsString( 'appearance_json text NULL', $sql );
		self::assertStringContainsString( 'retrieval_source_id bigint(20) unsigned NULL', $sql );
		self::assertStringContainsString( 'retrieval_collection_id varchar(128) NULL', $sql );
	}
}
