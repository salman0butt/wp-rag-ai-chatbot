<?php
/**
 * M09 current-lease execution context.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Lease-scoped handler context for heartbeat, progress and cancellation.
 */
final class JobExecutionContext {
	/**
	 * Create one current-lease execution context.
	 *
	 * @param JobRepository $repository Durable queue repository.
	 * @param JobLease      $lease Current owned lease.
	 * @param Clock         $clock Injected deterministic clock.
	 * @param int           $lease_seconds Lease duration used for heartbeat extension.
	 */
	public function __construct(
		private readonly JobRepository $repository,
		private JobLease $lease,
		private readonly Clock $clock,
		private readonly int $lease_seconds
	) {
	}

	/**
	 * Extend and replace the current lease using the repository boundary.
	 */
	public function heartbeat(): JobLease {
		$this->lease = $this->repository->heartbeat( $this->lease, $this->clock->now(), $this->lease_seconds );
		return $this->lease;
	}

	/**
	 * Persist one bounded progress snapshot for the current lease.
	 *
	 * @param JobProgress $progress Safe bounded progress snapshot.
	 */
	public function update_progress( JobProgress $progress ): void {
		$this->repository->updateProgress( $this->lease, $progress, $this->clock->now() );
	}

	/**
	 * Return whether cooperative cancellation was requested for the current lease.
	 */
	public function cancellation_requested(): bool {
		return $this->repository->cancellationRequested( $this->lease );
	}
}
