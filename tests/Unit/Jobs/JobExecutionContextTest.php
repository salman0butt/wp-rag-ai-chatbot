<?php
/**
 * M09 lease-scoped execution context behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;

/**
 * Proves handler callbacks can mutate queue state only through the current lease.
 */
final class JobExecutionContextTest extends TestCase {
	/**
	 * Heartbeat delegates through the repository and returns the refreshed lease.
	 */
	public function test_heartbeat_delegates_through_current_lease(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 'worker-token' );
		$refreshed  = $this->lease( $now->modify( '+1 second' ), 'worker-token' );
		$repository = $this->createMock( JobRepository::class );
		$clock      = $this->createMock( Clock::class );

		$clock->method( 'now' )->willReturn( $now );
		$repository->expects( self::once() )
			->method( 'heartbeat' )
			->with( $lease, $now, 120 )
			->willReturn( $refreshed );

		$context = new JobExecutionContext( $repository, $lease, $clock, 120 );

		self::assertSame( $refreshed, $context->heartbeat() );
	}

	/**
	 * Progress delegates through the current lease and injected clock.
	 */
	public function test_progress_delegates_through_current_lease(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 'worker-token' );
		$progress   = new JobProgress( 2, 5, 'Embedding documents' );
		$repository = $this->createMock( JobRepository::class );
		$clock      = $this->createMock( Clock::class );

		$clock->method( 'now' )->willReturn( $now );
		$repository->expects( self::once() )
			->method( 'updateProgress' )
			->with( $lease, $progress, $now );

		$context = new JobExecutionContext( $repository, $lease, $clock, 120 );
		$context->update_progress( $progress );
	}

	/**
	 * Cancellation checks delegate through the current lease.
	 */
	public function test_cancellation_check_delegates_through_current_lease(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:00:00+00:00' );
		$lease      = $this->lease( $now, 'worker-token' );
		$repository = $this->createMock( JobRepository::class );
		$clock      = $this->createMock( Clock::class );

		$repository->expects( self::once() )
			->method( 'cancellationRequested' )
			->with( $lease )
			->willReturn( true );

		$context = new JobExecutionContext( $repository, $lease, $clock, 120 );

		self::assertTrue( $context->cancellation_requested() );
	}

	/**
	 * Build one valid running lease fixture.
	 *
	 * @param DateTimeImmutable $now Fixture timestamp.
	 * @param string            $owner Opaque lease owner.
	 */
	private function lease( DateTimeImmutable $now, string $owner ): JobLease {
		$job = new JobRecord(
			id: 1,
			job_key: 'job-0000000000000001',
			type: 'index_document',
			status: JobStatus::RUNNING,
			idempotency_key: null,
			payload: array( 'document_id' => 10 ),
			attempts: 1,
			max_attempts: 3,
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
