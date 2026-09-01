<?php
/**
 * WordPress database advisory migration lock.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Uses a deterministic MySQL/MariaDB named lock to serialize migrations.
 */
final class WpdbNamedMigrationLock implements MigrationLock {
	/**
	 * Deterministic advisory lock name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Create the site-scoped migration lock.
	 *
	 * @param Connection $connection Database connection.
	 */
	public function __construct( private readonly Connection $connection ) {
		$identity   = $connection->database_name() . '|' . $connection->prefix();
		$this->name = 'wp_rag_ai_migrate_' . substr( sha1( $identity ), 0, 40 );
	}

	/**
	 * Attempt to acquire the lock without waiting.
	 */
	public function acquire(): bool {
		$sql = $this->connection->prepare( 'SELECT GET_LOCK(%s, 0)', $this->name );

		return 1 === (int) $this->connection->get_var( $sql );
	}

	/**
	 * Release the current connection's named lock.
	 */
	public function release(): void {
		$sql = $this->connection->prepare( 'SELECT RELEASE_LOCK(%s)', $this->name );
		$this->connection->get_var( $sql );
	}
}
