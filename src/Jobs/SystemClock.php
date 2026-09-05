<?php
/**
 * Production UTC clock for M09 workers.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Supplies the current UTC instant to production workers.
 */
final class SystemClock implements Clock {
	/**
	 * Return the current UTC instant.
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
