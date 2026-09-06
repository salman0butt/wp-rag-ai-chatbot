<?php
/**
 * M10 lexical projection failure translation tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs\Sync;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobHandler;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;

/**
 * Proves transient local projection persistence failures reach the queue retry state machine safely.
 */
final class DocumentIndexProjectionFailureTest extends TestCase {
	/**
	 * Database projection outages are normalized as retryable without leaking storage detail.
	 */
	public function test_projection_database_failure_is_translated_retryably(): void {
		$now          = new DateTimeImmutable( '2026-09-05T16:00:00+00:00' );
		$payload      = new DocumentIndexJobPayload( 'doc-42', 42, 'collection-main', 'index-profile-default', 'generation-7' );
		$job          = $this->record( $now, $payload->to_array() );
		$lease        = new JobLease( $job, 'worker-token' );
		$repository   = $this->createMock( JobRepository::class );
		$clock        = $this->createMock( Clock::class );
		$dependencies = $this->createMock( DocumentIndexDependencies::class );

		$clock->method( 'now' )->willReturn( $now );
		$repository->method( 'cancellationRequested' )->willReturn( false );
		$repository->method( 'heartbeat' )->willReturn( $lease );
		$dependencies->method( 'plan' )->willReturn( new IndexPlan( array(), array(), array(), array(), array() ) );
		$dependencies->method( 'execute' )->willThrowException( new DatabaseException( 'private SQL host detail' ) );

		try {
			( new DocumentIndexJobHandler( $dependencies ) )->handle(
				$job,
				new JobExecutionContext( $repository, $lease, $clock, 120 )
			);
			self::fail( 'Projection database failure was not translated to a queue execution failure.' );
		} catch ( JobExecutionException $error ) {
			self::assertSame( 'index_projection_unavailable', $error->safe_code() );
			self::assertSame( 'Document search projection is temporarily unavailable.', $error->safe_message() );
			self::assertTrue( $error->retryable() );
			self::assertStringNotContainsString( 'private SQL', $error->safe_message() );
		}
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
