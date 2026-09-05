<?php
/**
 * M09 worker cancellation-priority regression test.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobHandlerRegistry;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\JobWorker;
use WpRagAiChatbot\Jobs\WorkerConfig;

/**
 * Proves cancellation wins before persisted job-type resolution.
 */
final class JobWorkerCancellationPriorityTest extends TestCase {
	/**
	 * A cancelled unknown type must end cancelled, not failed as unknown.
	 */
	public function test_cancellation_wins_before_unknown_handler_resolution(): void {
		$now        = new DateTimeImmutable( '2026-09-05T06:15:00+00:00' );
		$owner      = str_repeat( 'b', 64 );
		$job        = new JobRecord(
			id: 9,
			job_key: 'job-0000000000000009',
			type: 'unknown_type',
			status: JobStatus::RUNNING,
			idempotency_key: null,
			payload: array( 'document_id' => 10 ),
			attempts: 1,
			max_attempts: 3,
			available_at: $now,
			lease_owner: $owner,
			lease_expires_at: $now->modify( '+120 seconds' ),
			cancel_requested_at: $now,
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
		$lease      = new JobLease( $job, $owner );
		$repository = $this->createMock( JobRepository::class );
		$clock      = $this->createMock( Clock::class );

		$clock->method( 'now' )->willReturn( $now );
		$repository->method( 'claimNext' )->willReturn( $lease );
		$repository->method( 'cancellationRequested' )->with( $lease )->willReturn( true );
		$repository->expects( self::once() )->method( 'markCancelled' )->with( $lease, $now );
		$repository->expects( self::never() )->method( 'markFailed' );

		( new JobWorker( $repository, new JobHandlerRegistry(), $clock ) )->run( new WorkerConfig( 1, 20, 120 ) );
	}
}
