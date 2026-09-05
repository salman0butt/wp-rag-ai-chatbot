<?php
/**
 * Bounded M09 worker service.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use Throwable;

/**
 * Claims, executes and transitions persisted jobs within explicit bounds.
 */
final class JobWorker {
	private const UNKNOWN_TYPE_CODE       = 'unknown_job_type';
	private const UNKNOWN_TYPE_MESSAGE    = 'No registered handler is available for this job type.';
	private const UNEXPECTED_FAILURE_CODE = 'unexpected_failure';
	private const UNEXPECTED_FAILURE_MSG  = 'Job execution failed unexpectedly.';

	/**
	 * Create the bounded worker service.
	 *
	 * @param JobRepository      $repository Durable queue repository.
	 * @param JobHandlerRegistry $registry Explicit allowlisted handlers.
	 * @param Clock              $clock Injected deterministic clock.
	 */
	public function __construct(
		private readonly JobRepository $repository,
		private readonly JobHandlerRegistry $registry,
		private readonly Clock $clock
	) {
	}

	/**
	 * Execute one bounded worker invocation.
	 *
	 * @param WorkerConfig $config Validated worker bounds.
	 */
	public function run( WorkerConfig $config ): JobWorkerResult {
		$worker_token = bin2hex( random_bytes( 32 ) );
		$started_at   = $this->clock->now();
		$started      = 0;

		while ( $started < $config->max_jobs ) {
			$now = $this->clock->now();
			if ( $now->getTimestamp() - $started_at->getTimestamp() >= $config->start_budget_seconds ) {
				break;
			}

			$lease = $this->repository->claimNext( $worker_token, $now, $config->lease_seconds );
			if ( null === $lease ) {
				break;
			}

			++$started;
			$this->execute_lease( $lease, $config );
		}

		return new JobWorkerResult( $started );
	}

	/**
	 * Execute one current lease and persist exactly one terminal/retry transition.
	 *
	 * @param JobLease      $lease Current owned lease.
	 * @param WorkerConfig  $config Worker configuration.
	 */
	private function execute_lease( JobLease $lease, WorkerConfig $config ): void {
		try {
			$handler = $this->registry->for_type( $lease->job->type );
		} catch ( JobQueueException ) {
			$this->repository->markFailed(
				$lease,
				self::UNKNOWN_TYPE_CODE,
				self::UNKNOWN_TYPE_MESSAGE,
				$this->clock->now()
			);
			return;
		}

		try {
			$handler->handle(
				$lease->job,
				new JobExecutionContext( $this->repository, $lease, $this->clock, $config->lease_seconds )
			);
			$this->repository->complete( $lease, $this->clock->now() );
		} catch ( JobExecutionException $error ) {
			$this->persist_execution_failure( $lease, $error );
		} catch ( Throwable ) {
			$this->repository->markFailed(
				$lease,
				self::UNEXPECTED_FAILURE_CODE,
				self::UNEXPECTED_FAILURE_MSG,
				$this->clock->now()
			);
		}
	}

	/**
	 * Persist a normalized retryable or terminal handler failure.
	 *
	 * @param JobLease              $lease Current lease.
	 * @param JobExecutionException $error Safe normalized handler failure.
	 */
	private function persist_execution_failure( JobLease $lease, JobExecutionException $error ): void {
		$now = $this->clock->now();
		if ( $error->retryable() && $lease->job->attempts < $lease->job->max_attempts ) {
			$delay = RetryPolicy::delay_seconds( $lease->job->attempts );
			$this->repository->markRetry(
				$lease,
				$error->safe_code(),
				$error->safe_message(),
				$now->modify( '+' . $delay . ' seconds' ),
				$now
			);
			return;
		}

		$this->repository->markFailed(
			$lease,
			$error->safe_code(),
			$error->safe_message(),
			$now
		);
	}
}
