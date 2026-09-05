<?php
/**
 * Bounded terminal job cleanup service.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use DateTimeImmutable;

/**
 * Applies the hard M09 cleanup bound before storage access.
 */
final class JobCleanup {
	private const MAX_LIMIT = 500;

	/**
	 * Create the cleanup service.
	 *
	 * @param JobCleanupStore $store Terminal cleanup store.
	 */
	public function __construct( private readonly JobCleanupStore $store ) {
	}

	/**
	 * Delete terminal history before the cutoff within one bounded pass.
	 *
	 * @param DateTimeImmutable $before Cleanup cutoff.
	 * @param int               $limit Maximum rows to delete.
	 * @throws JobQueueException When the cleanup limit is invalid.
	 */
	public function prune( DateTimeImmutable $before, int $limit = self::MAX_LIMIT ): int {
		if ( $limit < 1 || $limit > self::MAX_LIMIT ) {
			throw new JobQueueException( 'Job cleanup limit must be between 1 and 500.' );
		}

		return $this->store->delete_terminal_before( $before, $limit );
	}
}
