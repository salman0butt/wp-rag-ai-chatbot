<?php
/**
 * Persisted job repository contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use DateTimeImmutable;

// phpcs:disable WordPress.NamingConventions -- Repository methods follow the approved M09 domain contract.
/**
 * Persistence boundary for the durable queue.
 */
interface JobRepository {
	/**
	 * Enqueue one validated job request for the supplied UTC time.
	 *
	 * @param JobRequest        $request Validated queue request.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function enqueue( JobRequest $request, DateTimeImmutable $now ): JobRecord;

	/**
	 * Claim the next due or recoverable job using one opaque worker token.
	 *
	 * @param string            $worker_token Opaque worker-owned lease token.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $lease_seconds Lease duration in seconds.
	 */
	public function claimNext( string $worker_token, DateTimeImmutable $now, int $lease_seconds ): ?JobLease;

	/**
	 * Extend the current lease while preserving the same owner.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param DateTimeImmutable $now Current UTC time.
	 * @param int               $lease_seconds Lease duration in seconds.
	 */
	public function heartbeat( JobLease $lease, DateTimeImmutable $now, int $lease_seconds ): JobLease;

	/**
	 * Persist monotonic progress for the current lease.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param JobProgress       $progress Bounded progress snapshot.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function updateProgress( JobLease $lease, JobProgress $progress, DateTimeImmutable $now ): void;

	/**
	 * Check whether cooperative cancellation has been requested.
	 *
	 * @param JobLease $lease Current lease.
	 */
	public function cancellationRequested( JobLease $lease ): bool;

	/**
	 * Request cancellation for one stable job identity.
	 *
	 * @param string            $job_key Stable opaque job key.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function requestCancellation( string $job_key, DateTimeImmutable $now ): JobRecord;

	/**
	 * Complete the current running lease as cancelled.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function markCancelled( JobLease $lease, DateTimeImmutable $now ): void;

	/**
	 * Complete a running job owned by the current lease.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function complete( JobLease $lease, DateTimeImmutable $now ): void;

	/**
	 * Place a running job into retry-wait state.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param string            $code Sanitized failure code.
	 * @param string            $message Sanitized failure message.
	 * @param DateTimeImmutable $available_at Earliest next claim time.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function markRetry( JobLease $lease, string $code, string $message, DateTimeImmutable $available_at, DateTimeImmutable $now ): void;

	/**
	 * Mark a running job terminally failed.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param string            $code Sanitized failure code.
	 * @param string            $message Sanitized failure message.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function markFailed( JobLease $lease, string $code, string $message, DateTimeImmutable $now ): void;
}
// phpcs:enable WordPress.NamingConventions
