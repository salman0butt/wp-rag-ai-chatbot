<?php
/**
 * WordPress schema version store.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Persists the applied schema version in a non-autoloaded WordPress option.
 */
final class WordPressSchemaVersionStore implements SchemaVersionStore {
	/**
	 * Get the current applied version.
	 */
	public function current(): int {
		return (int) get_option( DatabaseSchema::VERSION_OPTION, 0 );
	}

	/**
	 * Persist a successfully applied version.
	 *
	 * @param int $version Applied schema version.
	 * @throws DatabaseException When WordPress cannot persist the requested version.
	 */
	public function save( int $version ): void {
		if ( $this->current() === $version ) {
			return;
		}

		if ( false === update_option( DatabaseSchema::VERSION_OPTION, $version, false ) && $this->current() !== $version ) {
			throw new DatabaseException( 'Could not persist database schema version.' );
		}
	}
}
