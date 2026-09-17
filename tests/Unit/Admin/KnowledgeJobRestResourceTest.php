<?php
/**
 * M13 recoverable job admin resource tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobPayload;
use WpRagAiChatbot\Jobs\Sync\WordPressDocumentIndexDependencies;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/**
 * Verifies bounded safe job projections and lifecycle guards.
 */
final class KnowledgeJobRestResourceTest extends TestCase {
	/** Start WordPress function isolation. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/** Stop WordPress function isolation. */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** Job lists expose only bounded safe status fields. */
	public function test_list_projects_safe_fields_without_payload_or_lease_data(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::once() )
			->method( 'paginate' )
			->with( 1, 20 )
			->willReturn( new PagedResult( array( $this->job( JobStatus::FAILED ) ), 1, 1, 20 ) );

		$response = $this->invoke( new $resource_class( $reader, $this->repository(), $this->clock(), $this->sources(), $this->documents() ), 'list', array( 1, 20 ) );

		self::assertSame( 1, $response['total'] );
		self::assertSame( 'job-123', $response['items'][0]['job_key'] );
		self::assertSame( 'failed', $response['items'][0]['status'] );
		self::assertSame( 'provider_unavailable', $response['items'][0]['last_error_code'] );
		self::assertSame( 'Indexing provider is temporarily unavailable.', $response['items'][0]['last_error_message'] );

		self::assertArrayNotHasKey( 'payload', $response['items'][0] );
		self::assertArrayNotHasKey( 'idempotency_key', $response['items'][0] );
		self::assertArrayNotHasKey( 'lease_token', $response['items'][0] );
	}

	/** Unsupported terminal cancellation must not call the M09 mutation seam. */
	public function test_cancel_rejects_terminal_job_without_mutating_repository(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::once() )->method( 'findByKey' )->with( 'job-123' )->willReturn( $this->job( JobStatus::FAILED ) );
		$repository = $this->repository();
		$repository->expects( self::never() )->method( 'requestCancellation' );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock(), $this->sources(), $this->documents() ), 'cancel', array( 'job-123' ) );

		self::assertSame( 'invalid_transition', $response['error']['code'] );
	}

	/** Unsupported retry must not enqueue a new generation. */
	public function test_retry_rejects_non_failed_job_without_enqueuing(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::once() )->method( 'findByKey' )->with( 'job-123' )->willReturn( $this->job( JobStatus::QUEUED ) );
		$repository = $this->repository();
		$repository->expects( self::never() )->method( 'enqueue' );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock(), $this->sources(), $this->documents() ), 'retry', array( 'job-123' ) );

		self::assertSame( 'invalid_transition', $response['error']['code'] );
	}

	/** Failed document-index jobs retry by enqueuing through the existing M09 contract. */
	public function test_retry_failed_document_index_job_enqueues_new_generation(): void {
		$resource_class = $this->resource_class();
		$failed         = $this->job( JobStatus::FAILED, 'job-123', false );
		$reader         = $this->reader();
		$reader->expects( self::once() )->method( 'findByKey' )->with( 'job-123' )->willReturn( $failed );
		$sources = $this->sources();
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $this->source() );
		$documents = $this->documents();
		$documents->expects( self::once() )->method( 'findByKey' )->with( 'doc:42' )->willReturn( $this->document() );
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
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock( $now ), $sources, $documents ), 'retry', array( 'job-123' ) );

		self::assertSame( 'job-retry', $response['job_key'] );
		self::assertSame( 'queued', $response['status'] );
	}

	/** Failed source-sync jobs retry through the source-sync queue contract. */
	public function test_retry_failed_source_sync_job_enqueues_new_generation(): void {
		$resource_class = $this->resource_class();
		$failed         = $this->source_job( JobStatus::FAILED );
		$reader         = $this->reader();
		$reader->expects( self::once() )->method( 'findByKey' )->with( 'sync-source-job' )->willReturn( $failed );
		$sources = $this->sources();
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $this->source() );
		$repository = $this->repository();
		$now        = new DateTimeImmutable( '2026-09-08T18:45:00+00:00' );
		$repository->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static fn ( JobRequest $request ): bool => 'sync.source' === $request->type
						&& KnowledgeSourceSyncJobPayload::from_array( $request->payload )->to_array() === array(
							'source_id'        => 7,
							'collection_id'    => WordPressDocumentIndexDependencies::COLLECTION_ID,
							'configuration_id' => WordPressDocumentIndexDependencies::configuration_id(),
							'generation'       => 'source-generation-v1',
						)
						&& 3 === $request->max_attempts
				),
				$now
			)
			->willReturn( $this->source_job( JobStatus::QUEUED, 'sync-source-retry' ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock( $now ), $sources, $this->documents() ), 'retry', array( 'sync-source-job' ) );

		self::assertSame( 'sync-source-retry', $response['job_key'] );
		self::assertSame( 'queued', $response['status'] );
	}

	/** New indexing jobs accept only the established identifier-only M09 payload. */
	public function test_enqueue_document_index_job_reuses_existing_m09_enqueuer(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$repository     = $this->repository();
		$now            = new DateTimeImmutable( '2026-09-08T18:45:00+00:00' );
		$payload        = $this->request_payload();
		$expected       = $this->payload();
		$sources        = $this->sources();
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $this->source() );
		$documents = $this->documents();
		$documents->expects( self::once() )->method( 'findByKey' )->with( 'doc:42' )->willReturn( $this->document() );
		$repository->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static fn ( JobRequest $request ): bool => 'index.document' === $request->type
					&& $expected === $request->payload
				),
				$now
			)
			->willReturn( $this->job( JobStatus::QUEUED, 'job-new' ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_single_event' )->justReturn( true );

		$response = $this->invoke( new $resource_class( $reader, $repository, $this->clock( $now ), $sources, $documents ), 'enqueue', array( $payload ) );

		self::assertSame( 'job-new', $response['job_key'] );
		self::assertSame( 'queued', $response['status'] );
	}

	/** Browser-supplied server-owned indexing metadata must not reach the queue. */
	public function test_enqueue_rejects_browser_supplied_indexing_metadata(): void {
		$resource_class = $this->resource_class();
		$repository     = $this->repository();
		$repository->expects( self::never() )->method( 'enqueue' );
		$payload                  = $this->payload();
		$payload['collection_id'] = 'attacker-collection';

		$response = $this->invoke( new $resource_class( $this->reader(), $repository, $this->clock(), $this->sources(), $this->documents() ), 'enqueue', array( $payload ) );

		self::assertSame( 'invalid_request', $response['error']['code'] );
	}

	/** Invalid list bounds are rejected before persisted reads. */
	public function test_list_rejects_unbounded_page_size_before_repository_access(): void {
		$resource_class = $this->resource_class();
		$reader         = $this->reader();
		$reader->expects( self::never() )->method( 'paginate' );

		$response = $this->invoke( new $resource_class( $reader, $this->repository(), $this->clock(), $this->sources(), $this->documents() ), 'list', array( 1, 101 ) );

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

	/** Build the persisted source repository seam. */
	private function sources(): KnowledgeSourceRepository&MockObject {
		return $this->createMock( KnowledgeSourceRepository::class );
	}

	/** Build the persisted document repository seam. */
	private function documents(): DocumentRepository&MockObject {
		return $this->createMock( DocumentRepository::class );
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

	/**
	 * Build one persisted source-sync job fixture.
	 *
	 * @param JobStatus $status Job status.
	 * @param string    $job_key Stable job identity.
	 */
	private function source_job( JobStatus $status, string $job_key = 'sync-source-job' ): JobRecord {
		$now = new DateTimeImmutable( '2026-09-08T18:40:00+00:00' );

		return new JobRecord(
			2,
			$job_key,
			'sync.source',
			$status,
			'SOURCE-IDEMPOTENCY-SECRET',
			array(
				'source_id'        => 7,
				'collection_id'    => WordPressDocumentIndexDependencies::COLLECTION_ID,
				'configuration_id' => WordPressDocumentIndexDependencies::configuration_id(),
				'generation'       => 'source-generation-v1',
			),
			1,
			3,
			$now,
			'SOURCE-LEASE-SECRET',
			$now->modify( '+2 minutes' ),
			null,
			3,
			1,
			'Normalizing knowledge source',
			'source_sync_invalid',
			'Knowledge source could not be synchronized safely.',
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
			'collection_id'    => WordPressDocumentIndexDependencies::COLLECTION_ID,
			'configuration_id' => WordPressDocumentIndexDependencies::configuration_id(),
			'generation'       => 'source-generation-v1',
		);
	}

	/** Return the browser-owned identifier-only request shape. */
	private function request_payload(): array {
		return array(
			'document_key' => 'doc:42',
			'source_id'    => 7,
		);
	}

	/** Return a source carrying the current fixed semantic profile. */
	private function source(): KnowledgeSourceRecord {
		$now = new DateTimeImmutable( '2026-09-08T18:40:00+00:00' );

		return new KnowledgeSourceRecord(
			7,
			'source:7',
			'manual_text',
			null,
			'Support guide',
			null,
			'active',
			array( 'semantic_retrieval' => WordPressDocumentIndexDependencies::semantic_configuration() ),
			'source-generation-v1',
			null,
			$now,
			$now
		);
	}

	/** Return a document whose persisted lineage belongs to the current source. */
	private function document(): DocumentRecord {
		$now = new DateTimeImmutable( '2026-09-08T18:40:00+00:00' );

		return new DocumentRecord(
			42,
			'doc:42',
			7,
			null,
			'post',
			'Support guide',
			null,
			'Support content',
			array(),
			'source-generation-v1',
			hash( 'sha256', 'Support content' ),
			null,
			'public',
			$now,
			$now
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
