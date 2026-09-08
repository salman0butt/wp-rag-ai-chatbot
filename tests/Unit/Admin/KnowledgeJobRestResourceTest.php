<?php
/**
 * M13 recoverable job admin resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;

/**
 * Verifies bounded safe job projections and lifecycle guards.
 */
final class KnowledgeJobRestResourceTest extends TestCase {
	/** Job lists expose only bounded safe status fields. */
	public function test_list_projects_safe_fields_without_payload_or_lease_data(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::once() )
			->method( 'paginate' )
			->with( 1, 20 )
			->willReturn( new PagedResult( array( $this->job( JobStatus::FAILED ) ), 1, 1, 20 ) );

		$response = $this->invoke( new $resource_class( $reader, $this->repository(), $this->clock() ), 'list', array( 1, 20 ) );

		self::assertSame( 1, $response['total'] );
		self::assertSame( 'job-123', $response['items'][0]['job_key'] );
		self::assertSame( 'failed', $response['items'][0]['status'] );
		self::assertSame( 'provider_unavailable', $response['items'][0]['last_error_code'] );
		self::assertSame( 'Indexing provider is temporarily unavailable.', $response['items'][0]['last_error_message'] );

		$serialized = wp_json_encode( $response );
		self::assertIsString( $serialized );
		self::assertStringNotContainsString( 'PAYLOAD-SECRET', $serialized );
		self::assertStringNotContainsString( 'IDEMPOTENCY-SECRET', $serialized );
		self::assertStringNotContainsString( 'LEASE-SECRET', $serialized );
	}

	/** Unsupported terminal cancellation must not call the M09 mutation seam. */
	public function test_cancel_rejects_terminal_job_without_mutating_repository(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::once() )->method( 'findByKey' )->with( 'job-123' )->willReturn( $this->job( JobStatus::FAILED ) );
		$repository = $this->repository();
		$repository->expects( self::never() )->method( 'requestCancellation' );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock() ), 'cancel', array( 'job-123' ) );

		self::assertSame( 'invalid_transition', $response['error']['code'] );
	}

	/** Unsupported retry must not enqueue a new generation. */
	public function test_retry_rejects_non_failed_job_without_enqueuing(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::once() )->method( 'findByKey' )->with( 'job-123' )->willReturn( $this->job( JobStatus::QUEUED ) );
		$repository = $this->repository();
		$repository->expects( self::never() )->method( 'enqueue' );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock() ), 'retry', array( 'job-123' ) );

		self::assertSame( 'invalid_transition', $response['error']['code'] );
	}

	/** Failed document-index jobs retry by enqueuing through the existing M09 contract. */
	public function test_retry_failed_document_index_job_enqueues_new_generation(): void {
		$resource_class = $this->resource_class();
		$failed         = $this->job( JobStatus::FAILED, 'job-123', false );
		$reader         = $this->reader();
		$reader->expects( self::once() )->method( 'findByKey' )->with( 'job-123' )->willReturn( $failed );
		$repository = $this->repository();
		$now        = new DateTimeImmutable( '2026-09-08T18:45:00+00:00' );
		$repository->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static fn ( JobRequest $request ): bool => 'index.document' === $request->type
						&& $failed->payload === $request->payload
						&& 3 === $request->max_attempts
				),
				$now
			)
			->willReturn( $this->job( JobStatus::QUEUED, 'job-retry' ) );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock( $now ) ), 'retry', array( 'job-123' ) );

		self::assertSame( 'job-retry', $response['job_key'] );
		self::assertSame( 'queued', $response['status'] );
	}

	/** New indexing jobs accept only the established identifier-only M09 payload. */
	public function test_enqueue_document_index_job_reuses_existing_m09_enqueuer(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$repository     = $this->repository();
		$now            = new DateTimeImmutable( '2026-09-08T18:45:00+00:00' );
		$payload        = $this->payload();
		$repository->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static fn ( JobRequest $request ): bool => 'index.document' === $request->type
						&& $payload === $request->payload
					),
				$now
			)
			->willReturn( $this->job( JobStatus::QUEUED, 'job-new' ) );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock( $now ) ), 'enqueue', array( $payload ) );

		self::assertSame( 'job-new', $response['job_key'] );
		self::assertSame( 'queued', $response['status'] );
	}

	/** Invalid list bounds are rejected before persisted reads. */
	public function test_list_rejects_unbounded_page_size_before_repository_access(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::never() )->method( 'paginate' );

		$response = $this->invoke( new $resource_class( $reader, $this->repository(), $this->clock() ), 'list', array( 1, 101 ) );

		self::assertSame( 'invalid_request', $response['error']['code'] );
	}

	/** Return the dynamically loaded resource class so RED reaches PHPUnit rather than static analysis. */
	private function resource_class(): string {
		$class = 'WpRagAiChatbot\\Admin\\Rest\\KnowledgeJobRestResource';
		self::assertTrue( class_exists( $class ), 'KnowledgeJobRestResource must exist.' );

		return $class;
	}

	/** Build a mock of the Task 3 read-only job inspection contract. */
	private function reader(): MockObject {
		$interface = 'WpRagAiChatbot\\Jobs\\JobReadRepository';
		self::assertTrue( interface_exists( $interface ), 'JobReadRepository must exist.' );

		return $this->createMock( $interface );
	}

	/** Build the existing M09 mutation repository mock. */
	private function repository(): JobRepository&MockObject {
		return $this->createMock( JobRepository::class );
	}

	/**
	 * Build a deterministic queue clock.
	 *
	 * @param DateTimeImmutable|null $now Fixed current time.
	 */
	private function clock( ?DateTimeImmutable $now = null ): Clock&MockObject {
		$clock = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( $now ?? new DateTimeImmutable( '2026-09-08T18:45:00+00:00' ) );

		return $clock;
	}

	/**
	 * Build one persisted M09 job fixture containing fields that must remain server-side.
	 *
	 * @param JobStatus $status Persisted job status.
	 * @param string    $job_key Stable job identity.
	 * @param bool      $include_secret Whether to add a projection-only secret sentinel.
	 */
	private function job( JobStatus $status, string $job_key = 'job-123', bool $include_secret = true ): JobRecord {
		$now     = new DateTimeImmutable( '2026-09-08T18:40:00+00:00' );
		$payload = $this->payload();
		if ( $include_secret ) {
			$payload['secret'] = 'PAYLOAD-SECRET';
		}

		return new JobRecord(
			1,
			$job_key,
			'index.document',
			$status,
			'IDEMPOTENCY-SECRET',
			$payload,
			1,
			3,
			$now,
			'LEASE-SECRET',
			$now->modify( '+2 minutes' ),
			null,
			3,
			10,
			'Indexing 3 of 10',
			'provider_unavailable',
			'Indexing provider is temporarily unavailable.',
			$now->modify( '-1 minute' ),
			$status->terminal() ? $now : null,
			$now->modify( '-5 minutes' ),
			$now
		);
	}

	/** Return the exact identifier-only document-index payload. */
	private function payload(): array {
		return array(
			'document_key'     => 'doc:42',
			'source_id'        => 7,
			'collection_id'    => 'collection-main',
			'configuration_id' => 'config-default',
			'generation'       => 'v1',
		);
	}

	/**
	 * Invoke a dynamic resource method and assert the stable array boundary.
	 *
	 * @param object            $target Dynamic resource instance.
	 * @param string            $method Method name.
	 * @param array<int, mixed> $arguments Invocation arguments.
	 */
	private function invoke( object $target, string $method, array $arguments ): array {
		$response = call_user_func_array( array( $target, $method ), $arguments );
		self::assertIsArray( $response );

		return $response;
	}
}
