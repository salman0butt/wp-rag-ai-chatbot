<?php
/**
 * Failing migration fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

use RuntimeException;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Migration;

/**
 * Migration fixture that fails deterministically.
 */
final class FailingMigration implements Migration {
	/**
	 * Create a failing migration.
	 *
	 * @param int $migration_version Migration version.
	 */
	public function __construct( private readonly int $migration_version ) {
	}

	/**
	 * Get migration version.
	 */
	public function version(): int {
		return $this->migration_version;
	}

	/**
	 * Fail migration execution.
	 *
	 * @param Connection $connection Unused test connection.
	 * @throws RuntimeException Always.
	 */
	public function up( Connection $connection ): void {
		throw new RuntimeException( 'migration failed' );
	}
}
