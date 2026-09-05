<?php
/**
 * Bounded M09 worker configuration contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Holds validated bounds for one worker invocation.
 */
final class WorkerConfig {
	private const MIN_JOBS                 = 1;
	private const MAX_JOBS                 = 100;
	private const MIN_START_BUDGET_SECONDS = 1;
	private const MAX_START_BUDGET_SECONDS = 300;
	private const MIN_LEASE_SECONDS        = 30;
	private const MAX_LEASE_SECONDS        = 900;

	/**
	 * Create validated worker bounds.
	 *
	 * @param int $max_jobs Maximum jobs one invocation may start.
	 * @param int $start_budget_seconds Maximum seconds before refusing to start another job.
	 * @param int $lease_seconds Lease duration in seconds.
	 * @throws JobQueueException When any configured bound is invalid.
	 */
	public function __construct(
		public readonly int $max_jobs = 10,
		public readonly int $start_budget_seconds = 20,
		public readonly int $lease_seconds = 120
	) {
		if ( $max_jobs < self::MIN_JOBS || $max_jobs > self::MAX_JOBS ) {
			throw new JobQueueException( 'Worker max jobs must be between 1 and 100.' );
		}
		if ( $start_budget_seconds < self::MIN_START_BUDGET_SECONDS || $start_budget_seconds > self::MAX_START_BUDGET_SECONDS ) {
			throw new JobQueueException( 'Worker start budget must be between 1 and 300 seconds.' );
		}
		if ( $lease_seconds < self::MIN_LEASE_SECONDS || $lease_seconds > self::MAX_LEASE_SECONDS ) {
			throw new JobQueueException( 'Worker lease duration must be between 30 and 900 seconds.' );
		}
	}
}
