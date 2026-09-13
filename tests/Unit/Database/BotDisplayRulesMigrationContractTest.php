<?php
/**
 * M15 bot display-rules migration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\Migrations\V013AddBotDisplayRules;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Tests\Support\Database\RecordingConnection;

/** Defines schema version thirteen for persisted bot display rules. */
final class BotDisplayRulesMigrationContractTest extends TestCase {
	/** V013 adds display-rule storage without dropping prior bot configuration fields. */
	public function test_bot_display_rules_are_part_of_schema_version_thirteen(): void {
		self::assertTrue(
			class_exists( V013AddBotDisplayRules::class ),
			'M15 Task 2B requires V013AddBotDisplayRules.'
		);
		self::assertSame( 13, DatabaseSchema::VERSION );

		$connection = new RecordingConnection();
		$migration  = new V013AddBotDisplayRules( new TableNames( 'wp_' ) );

		self::assertSame( 13, $migration->version() );
		$migration->up( $connection );
		self::assertCount( 1, $connection->db_delta_queries );

		$sql = $connection->db_delta_queries[0];
		self::assertStringContainsString( 'appearance_json text NULL', $sql );
		self::assertStringContainsString( 'retrieval_source_id bigint(20) unsigned NULL', $sql );
		self::assertStringContainsString( 'retrieval_collection_id varchar(128) NULL', $sql );
		self::assertStringContainsString( 'display_rules_json text NULL', $sql );
	}
}
