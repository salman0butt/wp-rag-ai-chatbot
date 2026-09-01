<?php
/**
 * Migration lock fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Database;

use WpRagAiChatbot\Database\MigrationLock;

/**
 * In-memory migration lock with deterministic acquisition behavior.
 */
final class FakeMigrationLock implements MigrationLock {
	/**
	 * Whether acquisition was attempted.
	 *
	 * @var bool
	 */
	public bool $attempted = false;

	/**
	 * Whether release was called.
	 *
	 * @var bool
	 */
	public bool $released = false;

	/**
	 * Create a lock result fixture.
	 *
	 * @param bool $acquired Whether acquisition succeeds.
	 */
	public function __construct( private readonly bool $acquired ) {
	}

	/**
	 * Attempt lock acquisition.
	 */
	public function acquire(): bool {
		$this->attempted = true;
		return $this->acquired;
	}

	/**
	 * Release the lock.
	 */
	public function release(): void {
		$this->released = true;
	}
}
