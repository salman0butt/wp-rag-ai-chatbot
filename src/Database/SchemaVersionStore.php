<?php
/**
 * Schema version store contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Persists the last successfully applied schema version.
 */
interface SchemaVersionStore {
	/**
	 * Get the current applied version.
	 */
	public function current(): int;

	/**
	 * Persist a successfully applied version.
	 *
	 * @param int $version Applied schema version.
	 */
	public function save( int $version ): void;
}
