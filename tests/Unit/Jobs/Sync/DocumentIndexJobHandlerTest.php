<?php
/**
 * M09 document-index job handler orchestration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs\Sync;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobHandler;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;

/**
 * Proves the synchronization handler delegates through bounded server-side dependencies.
 */
final class DocumentIndexJobHandlerTest extends TestCase {
	/**
	 * Handler checks cancellation, reports progress, heartbeats and executes the accepted plan.
	 */
	public function test_handler_delegates_plan_and_execution_with_checkpoints(): void {
		$now          = new DateTimeImmutable( '2026-09-05T10:00:00+00:00' );
		$payload      = new DocumentIndexJobPayload( 'doc-42', 42, 'collection-main', 'index-profile-default', 'generation-7' );
		$job          = $this->record( $now, $payload->to_array() );
		$lease        = new JobLease( $job, 'worker-token' );
		$plan         = new IndexPlan( array(), array(), array(), array(), array() );
		$dependencies = $this->createMock( DocumentIndexDependencies::class );
		$repository   = $this->createMock( JobRepository::class );
		$clock        = $this->createMock( Clock::class );

		$clock->method( 'now' )->willReturn( $now );
		$repository->expects( self::exactly( 2 ) )
			->method( 'cancellationRequested' )
			->with( $lease )
			->willReturn( false );
		$repository->expects( self::once() )
			->method( 'heartbeat' )
			->with( $lease, $now, 120 )
			->willReturn( $lease );
		$repository->expects( self::exactly( 3 ) )
			->method( 'updateProgress' )
			->withAnyParameters();
		$dependencies->expects( self::once() )
			->method( 'plan' )
			->with( $payload )
			->willReturn( $plan );
		$dependencies->expects( self::once() )
			->method( 'execute' )
			->with( $payload, $plan );

		$handler = new DocumentIndexJobHandler( $dependencies );
		$context = new JobExecutionContext( $repository, $lease, $clock, 120 );

		self::assertSame( 'index.document', $handler->type() );
		$handler->handle( $job, $context );
	}

	/**
	 * Build one running document-index job fixture.
	 *
	 * @param DateTimeImmutable    $now Current fixture time.
	 * @param array<string, mixed> $payload Persisted synchronization payload.
	 */
	private function record( DateTimeImmutable $now, array $payload ): JobRecord {
		return new JobRecord(
			id: 1,
			job_key: 'job-0000000000000001',
			type: 'index.document',
			status: JobStatus::RUNNING,
			idempotency_key: 'index-document-' . str_repeat( 'a', 64 ),
			payload: $payload,
			attempts: 1,
			max_attempts: 3,
			available_at: $now,
			lease_owner: 'worker-token',
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
	}
}
