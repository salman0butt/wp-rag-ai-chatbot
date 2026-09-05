<?php
/**
 * WordPress database job idempotency lock.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Uses a deterministic site- and key-scoped named lock for idempotent enqueue.
 */
final class WpdbNamedJobIdempotencyLock implements JobIdempotencyLock {
	/**
	 * Deterministic advisory lock name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Create the enqueue-deduplication lock.
	 *
	 * @param Connection $connection Database connection.
	 * @param string     $type Validated job type.
	 * @param string     $idempotency_key Validated idempotency key.
	 */
	public function __construct(
		private readonly Connection $connection,
		string $type,
		string $idempotency_key
	) {
		$identity   = $connection->database_name() . '|' . $connection->prefix() . '|' . $type . '|' . $idempotency_key;
		$this->name = 'wp_rag_ai_job_' . substr( hash( 'sha256', $identity ), 0, 48 );
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
