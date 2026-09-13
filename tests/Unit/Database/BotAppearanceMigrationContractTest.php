<?php
/**
 * M14 bot appearance migration contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Migrations\V011AddBotAppearance;
use WpRagAiChatbot\Database\TableNames;

/**
 * Defines the schema identity for bot-scoped appearance persistence.
 */
final class BotAppearanceMigrationContractTest extends TestCase {
	/** Task 2A owns the version-eleven bots-table migration. */
	public function test_bot_appearance_remains_migration_version_eleven(): void {
		self::assertTrue(
			class_exists( V011AddBotAppearance::class ),
			'M14 Task 2A requires V011AddBotAppearance.'
		);

		$migration = new V011AddBotAppearance( new TableNames( 'wp_' ) );
		self::assertSame( 11, $migration->version() );
	}
}
