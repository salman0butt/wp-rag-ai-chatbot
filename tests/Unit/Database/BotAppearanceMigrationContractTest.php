<?php
/**
 * M14 bot appearance migration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseSchema;
use WpRagAiChatbot\Database\Migrations\V011AddBotAppearance;
use WpRagAiChatbot\Database\TableNames;

/**
 * Defines the schema identity for bot-scoped appearance persistence.
 */
final class BotAppearanceMigrationContractTest extends TestCase {
	/** Task 2A advances the schema and owns one bots-table migration. */
	public function test_bot_appearance_is_part_of_schema_version_eleven(): void {
		self::assertTrue(
			class_exists( V011AddBotAppearance::class ),
			'M14 Task 2A requires V011AddBotAppearance.'
		);
		self::assertSame( 11, DatabaseSchema::VERSION );

		$migration = new V011AddBotAppearance( new TableNames( 'wp_' ) );
		self::assertSame( 11, $migration->version() );
	}
}
