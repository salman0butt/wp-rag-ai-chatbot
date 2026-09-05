<?php
/**
 * M09 bounded worker state-machine behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\JobHandler;
use WpRagAiChatbot\Jobs\JobHandlerRegistry;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\JobWorker;
use WpRagAiChatbot\Jobs\WorkerConfig;

/**
 * Defines Task 3 worker execution, retry and failure transitions.
 */
final class JobWorkerBehaviorTest extends TestCase {
	/**
	 * Successful handlers complete the current lease exactly once.
	 */
	public function test_successful_handler_completes_current_lease(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 1, 3 );
		$repository = $this->createMock( JobRepository::class );
		$handler    = $this->handler( 'index_document' );
		$registry   = new JobHandlerRegistry();
		$registry->register( $handler );

		$repository->expects( self::once() )->method( 'claimNext' )
			->with( self::callback( static fn ( string $token ): bool => 1 === preg_match( '/^[a-f0-9]{64}$/', $token ) ), $now, 120 )
			->willReturn( $lease );
		$handler->expects( self::once() )->method( 'handle' )->with( $lease->job, self::isInstanceOf( 'WpRagAiChatbot\\Jobs\\JobExecutionContext' ) );
		$repository->expects( self::once() )->method( 'complete' )->with( $lease, $now );

		$result = ( new JobWorker( $repository, $registry, $this->clock( $now ) ) )->run( new WorkerConfig( 1, 20, 120 ) );

		self::assertSame( 1, $result->started_jobs );
	}

	/**
	 * Retryable failures schedule deterministic retry while attempts remain.
	 */
	public function test_retryable_failure_schedules_retry_when_attempts_remain(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 1, 3 );
		$repository = $this->createMock( JobRepository::class );
		$handler    = $this->handler( 'index_document' );
		$registry   = new JobHandlerRegistry();
		$registry->register( $handler );

		$repository->method( 'claimNext' )->willReturn( $lease );
		$handler->method( 'handle' )->willThrowException( new JobExecutionException( 'provider_timeout', 'Temporary provider failure.', true ) );
		$repository->expects( self::once() )->method( 'markRetry' )
			->with( $lease, 'provider_timeout', 'Temporary provider failure.', $now->modify( '+30 seconds' ), $now );

		( new JobWorker( $repository, $registry, $this->clock( $now ) ) )->run( new WorkerConfig( 1, 20, 120 ) );
	}

	/**
	 * Retryable failures become terminal on the final permitted attempt.
	 */
	public function test_retryable_failure_on_final_attempt_is_terminal(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 3, 3 );
		$repository = $this->createMock( JobRepository::class );
		$handler    = $this->handler( 'index_document' );
		$registry   = new JobHandlerRegistry();
		$registry->register( $handler );

		$repository->method( 'claimNext' )->willReturn( $lease );
		$handler->method( 'handle' )->willThrowException( new JobExecutionException( 'provider_timeout', 'Temporary provider failure.', true ) );
		$repository->expects( self::once() )->method( 'markFailed' )
			->with( $lease, 'provider_timeout', 'Temporary provider failure.', $now );
		$repository->expects( self::never() )->method( 'markRetry' );

		( new JobWorker( $repository, $registry, $this->clock( $now ) ) )->run( new WorkerConfig( 1, 20, 120 ) );
	}

	/**
	 * Unknown persisted types fail safely instead of resolving executable names.
	 */
	public function test_unknown_job_type_fails_with_constant_diagnostic(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 1, 3, 'unknown_type' );
		$repository = $this->createMock( JobRepository::class );
		$repository->method( 'claimNext' )->willReturn( $lease );
		$repository->expects( self::once() )->method( 'markFailed' )
			->with( $lease, 'unknown_job_type', 'No registered handler is available for this job type.', $now );

		( new JobWorker( $repository, new JobHandlerRegistry(), $this->clock( $now ) ) )->run( new WorkerConfig( 1, 20, 120 ) );
	}

	/**
	 * Unexpected throwables never persist their raw message.
	 */
	public function test_unexpected_throwable_is_sanitized_and_terminal(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 1, 3 );
		$repository = $this->createMock( JobRepository::class );
		$handler    = $this->handler( 'index_document' );
		$registry   = new JobHandlerRegistry();
		$registry->register( $handler );

		$repository->method( 'claimNext' )->willReturn( $lease );
		$handler->method( 'handle' )->willThrowException( new RuntimeException( 'secret-token-should-never-persist' ) );
		$repository->expects( self::once() )->method( 'markFailed' )
			->with( $lease, 'unexpected_failure', 'Job execution failed unexpectedly.', $now );

		( new JobWorker( $repository, $registry, $this->clock( $now ) ) )->run( new WorkerConfig( 1, 20, 120 ) );
	}

	/**
	 * Create one handler mock for a stable allowlisted type.
	 *
	 * @return JobHandler&MockObject
	 */
	private function handler( string $type ): JobHandler {
		$handler = $this->createMock( JobHandler::class );
		$handler->method( 'type' )->willReturn( $type );
		return $handler;
	}

	/**
	 * Create one deterministic clock mock.
	 *
	 * @return Clock&MockObject
	 */
	private function clock( DateTimeImmutable $now ): Clock {
		$clock = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( $now );
		return $clock;
	}

	/**
	 * Build one valid running lease fixture.
	 */
	private function lease( DateTimeImmutable $now, int $attempts, int $max_attempts, string $type = 'index_document' ): JobLease {
		$owner = str_repeat( 'a', 64 );
		$job   = new JobRecord(
			id: 1,
			job_key: 'job-0000000000000001',
			type: $type,
			status: JobStatus::RUNNING,
			idempotency_key: null,
			payload: array( 'document_id' => 10 ),
			attempts: $attempts,
			max_attempts: $max_attempts,
			available_at: $now,
			lease_owner: $owner,
			lease_expires_at: $now->modify( '+120 seconds' ),
			cancel_requested_at: null,
			progress_current: null,
			progress_total: null,
			progress_message: null,
			last_error_code: null,
			last_error_message: null,
			started_at: $now,
			completed_at: null,
			created_at: $now,
			updated_at: $now
		);

		return new JobLease( $job, $owner );
	}
}
