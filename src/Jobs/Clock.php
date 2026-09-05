<?php
/**
 * Deterministic M09 worker clock contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Supplies worker time without coupling Task 3 behavior to the system clock.
 */
interface Clock {
}
