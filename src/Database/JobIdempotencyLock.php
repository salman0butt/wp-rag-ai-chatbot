<?php
/**
 * Job enqueue idempotency lock contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

/**
 * Short advisory lock used only around idempotent enqueue lookup+insert.
 */
interface JobIdempotencyLock {
	/**
	 * Attempt to acquire the lock without waiting.
	 */
	public function acquire(): bool;

	/**
	 * Release the current connection's lock.
	 */
	public function release(): void;
}
