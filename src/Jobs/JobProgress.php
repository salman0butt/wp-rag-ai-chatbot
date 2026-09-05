<?php
/**
 * Bounded immutable job progress snapshot.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Represents one safe progress update for an active job attempt.
 */
final class JobProgress {
	/**
	 * Create a progress snapshot.
	 *
	 * @param int         $current Completed units.
	 * @param int         $total Total units; must be positive.
	 * @param string|null $message Optional bounded pre-sanitized message.
	 * @throws JobQueueException When progress is outside the approved bounds.
	 */
	public function __construct(
		public readonly int $current,
		public readonly int $total,
		public readonly ?string $message = null
	) {
		if ( $total < 1 || $current < 0 || $current > $total ) {
			throw new JobQueueException( 'Job progress is outside the allowed bounds.' );
		}

		if ( null !== $message && mb_strlen( $message ) > 191 ) {
			throw new JobQueueException( 'Job progress message exceeds the allowed length.' );
		}
	}
}
