<?php
/**
 * Database migration contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * One ordered, idempotent schema migration.
 */
interface Migration {
	/**
	 * Migration version number.
	 */
	public function version(): int;

	/**
	 * Apply the migration.
	 *
	 * @param Connection $connection Database connection.
	 */
	public function up( Connection $connection ): void;
}
