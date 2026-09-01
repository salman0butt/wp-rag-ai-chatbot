<?php
/**
 * Database migration lock contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Serializes schema migration execution.
 */
interface MigrationLock {
	/**
	 * Attempt to acquire the migration lock without waiting.
	 */
	public function acquire(): bool;

	/**
	 * Release the migration lock.
	 */
	public function release(): void;
}
