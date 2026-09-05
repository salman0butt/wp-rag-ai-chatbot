<?php
/**
 * Deterministic bounded retry policy for persisted jobs.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Computes the approved M09 retry delay from the current attempt number.
 */
final class RetryPolicy {
	/**
	 * Return the retry delay for an attempt in the validated 1..10 range.
	 *
	 * @param int $attempt Current persisted attempt number.
	 * @throws JobQueueException When the attempt is outside the queue contract.
	 */
	public static function delaySeconds( int $attempt ): int {
		if ( $attempt < 1 || $attempt > 10 ) {
			throw new JobQueueException( 'Job attempt must be between 1 and 10.' );
		}

		return min( 900, 30 * ( 2 ** ( $attempt - 1 ) ) );
	}
}
