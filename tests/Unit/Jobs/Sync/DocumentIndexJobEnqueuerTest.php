<?php
/**
 * M09 document-index job enqueue behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs\Sync;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;

/**
 * Proves synchronization enqueue uses a stable typed and idempotent request.
 */
final class DocumentIndexJobEnqueuerTest extends TestCase {
	/**
	 * Enqueue persists only the typed payload with a generation-derived idempotency key.
	 */
	public function test_enqueue_uses_stable_type_and_generation_idempotency(): void {
		$now        = new DateTimeImmutable( '2026-09-05T10:00:00+00:00' );
		$payload    = new DocumentIndexJobPayload( 'doc-42', 42, 'collection-main', 'index-profile-default', 'generation-7' );
		$repository = $this->createMock( JobRepository::class );
		$expected   = $this->record( $now, $payload->to_array() );

		$repository->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static function ( JobRequest $request ) use ( $payload ): bool {
						return 'index.document' === $request->type
							&& $payload->to_array() === $request->payload
							&& 3 === $request->max_attempts
							&& is_string( $request->idempotency_key )
							&& 1 === preg_match( '/^index-document-[a-f0-9]{64}$/', $request->idempotency_key );
					}
				),
				$now
			)
			->willReturn( $expected );

		$enqueuer = new DocumentIndexJobEnqueuer( $repository );

		self::assertSame( $expected, $enqueuer->enqueue( $payload, $now ) );
	}

	/**
	 * Build one queued record returned by the repository fixture.
	 *
	 * @param DateTimeImmutable    $now Current fixture time.
	 * @param array<string, mixed> $payload Persisted synchronization payload.
	 */
	private function record( DateTimeImmutable $now, array $payload ): JobRecord {
		return new JobRecord(
			id: 1,
			job_key: 'job-0000000000000001',
			type: 'index.document',
			status: JobStatus::QUEUED,
			idempotency_key: 'index-document-' . str_repeat( 'a', 64 ),
			payload: $payload,
			attempts: 0,
			max_attempts: 3,
			available_at: $now,
			lease_owner: null,
			lease_expires_at: null,
			cancel_requested_at: null,
			progress_current: null,
			progress_total: null,
			progress_message: null,
			last_error_code: null,
			last_error_message: null,
			started_at: null,
			completed_at: null,
			created_at: $now,
			updated_at: $now
		);
	}
}
