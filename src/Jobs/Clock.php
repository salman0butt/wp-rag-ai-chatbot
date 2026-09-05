<?php
/**
 * Deterministic M09 worker clock contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use DateTimeImmutable;

/**
 * Supplies worker time without coupling Task 3 behavior to the system clock.
 */
interface Clock {
	/**
	 * Return the current UTC-compatible instant.
	 */
	public function now(): DateTimeImmutable;
}
