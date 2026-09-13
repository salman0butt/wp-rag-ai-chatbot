<?php
/**
 * M12 bot table migration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\Migrations\V010CreateBotsTable;
use WpRagAiChatbot\Database\TableNames;

/**
 * Defines the schema identity and table-name contract for persisted bots.
 */
final class BotMigrationContractTest extends TestCase {
	/** Task 2 introduced schema version ten and the persistent bots table. */
	public function test_bot_table_was_introduced_in_schema_version_ten(): void {
		self::assertTrue( class_exists( V010CreateBotsTable::class ), 'M12 Task 2 requires V010CreateBotsTable.' );
		self::assertGreaterThanOrEqual( 10, DatabaseSchema::VERSION );

		$tables = new TableNames( 'wp_' );
		self::assertSame( 'wp_rag_ai_bots', $tables->bots() );
		self::assertContains( 'wp_rag_ai_bots', $tables->all() );
		self::assertSame( 10, ( new V010CreateBotsTable( $tables ) )->version() );
	}
}
