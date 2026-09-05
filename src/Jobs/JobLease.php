<?php
/**
 * Immutable active job lease.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Couples one hydrated running job with the opaque current lease token.
 */
final class JobLease {
	/**
	 * Create an active lease.
	 *
	 * @param JobRecord $job Hydrated running job.
	 * @param string    $lease_owner Opaque current lease token.
	 * @throws JobQueueException When the record is not owned by the supplied lease token.
	 */
	public function __construct(
		public readonly JobRecord $job,
		public readonly string $lease_owner
	) {
		if ( JobStatus::RUNNING !== $job->status || '' === $lease_owner || $job->lease_owner !== $lease_owner ) {
			throw new JobQueueException( 'Job lease does not match the current running owner.' );
		}
	}
}
