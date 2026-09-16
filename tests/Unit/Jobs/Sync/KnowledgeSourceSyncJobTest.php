<?php
/**
 * Production knowledge-source synchronization job tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs\Sync;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobHandler;
use WpRagAiChatbot\Jobs\Sync\KnowledgeSourceSyncJobPayload;
use WpRagAiChatbot\Jobs\WordPressJobCron;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSource;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceRegistry;
use WpRagAiChatbot\Knowledge\Sources\ManualTextSource;

// phpcs:disable WordPress.NamingConventions -- Assertions use the approved domain DTO properties.
/**
 * Proves source synchronization remains identifier-only, bounded, and queue-driven.
 */
final class KnowledgeSourceSyncJobTest extends TestCase {
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

	/** Malformed identifiers and non-exact persisted shapes never enter the queue contract. */
	public function test_payload_rejects_malformed_identifiers(): void {
		if ( ! class_exists( KnowledgeSourceSyncJobPayload::class ) ) {
			self::fail( 'KnowledgeSourceSyncJobPayload does not exist yet.' );
		}

		$invalid = array(
			array(
				'source_id'        => 0,
				'collection_id'    => 'wp-rag-default',
				'configuration_id' => 'gemini-embedding-001-3072-cosine-v1',
				'generation'       => 'generation-1',
			),
			array(
				'source_id'        => 7,
				'collection_id'    => '../escape',
				'configuration_id' => 'gemini-embedding-001-3072-cosine-v1',
				'generation'       => 'generation-1',
			),
			array(
				'source_id'        => 7,
				'collection_id'    => 'wp-rag-default',
				'configuration_id' => '',
				'generation'       => 'generation-1',
			),
			array(
				'source_id'        => 7,
				'collection_id'    => 'wp-rag-default',
				'configuration_id' => 'gemini-embedding-001-3072-cosine-v1',
				'generation'       => "bad\ngeneration",
			),
			array(
				'source_id'        => 7,
				'collection_id'    => 'wp-rag-default',
				'configuration_id' => 'gemini-embedding-001-3072-cosine-v1',
				'generation'       => 'generation-1',
				'provider_id'      => 'browser-override',
			),
		);

		foreach ( $invalid as $payload ) {
			try {
				KnowledgeSourceSyncJobPayload::from_array( $payload );
				self::fail( 'Malformed source-sync payload was accepted.' );
			} catch ( JobQueueException ) {
				self::addToAssertionCount( 1 );
			}
		}
	}

	/** A real registered source is normalized, saved, and converted to one existing document-index job. */
	public function test_valid_source_saves_document_and_enqueues_document_index_job(): void {
		if ( ! class_exists( KnowledgeSourceSyncJobHandler::class ) ) {
			self::fail( 'KnowledgeSourceSyncJobHandler does not exist yet.' );
		}

		$now       = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$source    = $this->source( $now );
		$payload   = $this->payload();
		$job       = $this->job( $now, 'sync.source', $payload->to_array() );
		$lease     = new JobLease( $job, 'worker-token' );
		$sources   = $this->createMock( KnowledgeSourceRepository::class );
		$documents = $this->createMock( DocumentRepository::class );
		$jobs      = $this->createMock( JobRepository::class );
		$clock     = $this->createMock( Clock::class );
		$progress  = array();

		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $source );
		$documents->expects( self::once() )->method( 'findByKey' )->with( 'manual:owner-guide' )->willReturn( null );
		$documents->expects( self::once() )
			->method( 'save' )
			->with(
				self::callback(
					static fn ( DocumentRecord $document ): bool => 'manual:owner-guide' === $document->documentKey
						&& 7 === $document->sourceId
						&& 'A concise production knowledge document.' === $document->content
				)
			)
			->willReturnCallback( static fn ( DocumentRecord $document ): DocumentRecord => $document->withId( 11 ) );

		$clock->method( 'now' )->willReturn( $now );
		$jobs->method( 'cancellationRequested' )->willReturn( false );
		$jobs->method( 'heartbeat' )->willReturn( $lease );
		$jobs->method( 'updateProgress' )->willReturnCallback(
			static function ( JobLease $current_lease, JobProgress $snapshot ) use ( &$progress ): void {
				unset( $current_lease );
				$progress[] = $snapshot;
			}
		);
		$jobs->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static fn ( JobRequest $request ): bool => 'index.document' === $request->type
						&& array(
							'document_key'     => 'manual:owner-guide',
							'source_id'        => 7,
							'collection_id'    => 'wp-rag-default',
							'configuration_id' => 'gemini-embedding-001-3072-cosine-v1',
							'generation'       => 'generation-1',
						) === $request->payload
				),
				$now
			)
			->willReturn( $this->job( $now, 'index.document', array() ) );

		$registry = new KnowledgeSourceRegistry();
		$registry->register( new ManualTextSource() );
		$handler = new KnowledgeSourceSyncJobHandler(
			$sources,
			$registry,
			$documents,
			new DocumentIndexJobEnqueuer( $jobs ),
			$clock
		);

		$handler->handle( $job, new JobExecutionContext( $jobs, $lease, $clock, 120 ) );

		self::assertSame( 'sync.source', $handler->type() );
		self::assertNotEmpty( $progress );
		self::assertSame( 0, $progress[0]->current );
		self::assertSame( 1, $progress[ count( $progress ) - 1 ]->current );
		self::assertSame( 1, $progress[ count( $progress ) - 1 ]->total );
	}

	/** A deleted source becomes a stable terminal queue failure without document or child-job writes. */
	public function test_missing_source_fails_safely(): void {
		if ( ! class_exists( KnowledgeSourceSyncJobHandler::class ) ) {
			self::fail( 'KnowledgeSourceSyncJobHandler does not exist yet.' );
		}

		$fixture   = $this->handler_fixture( null );
		$documents = $fixture['documents'];
		$jobs      = $fixture['jobs'];
		$documents->expects( self::never() )->method( 'save' );
		$jobs->expects( self::never() )->method( 'enqueue' );

		try {
			$fixture['handler']->handle( $fixture['job'], $fixture['context'] );
			self::fail( 'Missing source did not fail closed.' );
		} catch ( JobExecutionException $error ) {
			self::assertSame( 'source_sync_not_found', $error->safe_code() );
			self::assertSame( 'Knowledge source is no longer available.', $error->safe_message() );
			self::assertFalse( $error->retryable() );
		}
	}

	/** A stale or browser-invented profile cannot index under the fixed production collection. */
	public function test_source_configuration_mismatch_fails_before_normalization(): void {
		if ( ! class_exists( KnowledgeSourceSyncJobHandler::class ) ) {
			self::fail( 'KnowledgeSourceSyncJobHandler does not exist yet.' );
		}

		$now                    = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$source                 = $this->source( $now );
		$semantic               = $source->config['semantic_retrieval'];
		$semantic['dimensions'] = 1536;
		$source                 = new KnowledgeSourceRecord(
			$source->id,
			$source->sourceKey,
			$source->sourceType,
			$source->externalId,
			$source->title,
			$source->canonicalUrl,
			$source->status,
			array_merge( $source->config, array( 'semantic_retrieval' => $semantic ) ),
			$source->sourceHash,
			$source->lastSyncedAt,
			$source->createdAt,
			$source->updatedAt
		);
		$fixture                = $this->handler_fixture( $source );

		try {
			$fixture['handler']->handle( $fixture['job'], $fixture['context'] );
			self::fail( 'Mismatched source profile was accepted.' );
		} catch ( JobExecutionException $error ) {
			self::assertSame( 'source_sync_configuration_mismatch', $error->safe_code() );
			self::assertFalse( $error->retryable() );
		}
	}

	/** A source-normalization failure remains safe while identifying the failed boundary. */
	public function test_source_normalization_failure_uses_content_failure_code(): void {
		$now     = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$source  = $this->source( $now );
		$fixture = $this->handler_fixture( $source );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $source );
		$registry       = new KnowledgeSourceRegistry();
		$source_adapter = $this->createMock( KnowledgeSource::class );
		$source_adapter->method( 'type' )->willReturn( 'manual_text' );
		$source_adapter->method( 'documents' )->willThrowException( new KnowledgeSourceException( 'private source detail' ) );
		$registry->register( $source_adapter );
		$handler = new KnowledgeSourceSyncJobHandler(
			$sources,
			$registry,
			$fixture['documents'],
			new DocumentIndexJobEnqueuer( $fixture['jobs'] ),
			$this->createMock( Clock::class )
		);

		try {
			$handler->handle( $fixture['job'], $fixture['context'] );
			self::fail( 'Source-normalization failure did not fail closed.' );
		} catch ( JobExecutionException $error ) {
			self::assertSame( 'source_sync_content_invalid', $error->safe_code() );
			self::assertSame( 'WordPress content could not be normalized safely.', $error->safe_message() );
			self::assertFalse( $error->retryable() );
		}
	}

	/** Invalid normalized document identifiers fail before entering the child-job queue. */
	public function test_invalid_document_queue_payload_uses_document_queue_failure_code(): void {
		$now     = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$source  = $this->source( $now );
		$fixture = $this->handler_fixture( $source );
		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::once() )->method( 'findById' )->with( 7 )->willReturn( $source );
		$document = new DocumentRecord(
			null,
			'bad document key',
			7,
			'bad-document',
			'manual_text',
			'Invalid document',
			null,
			'Invalid document content',
			array(),
			'generation-1',
			hash( 'sha256', 'Invalid document content' ),
			null,
			'public',
			$now,
			$now
		);
		$fixture['documents']->expects( self::once() )->method( 'findByKey' )->with( 'bad document key' )->willReturn( null );
		$fixture['documents']->expects( self::once() )->method( 'save' )->with( $document )->willReturn( $document->withId( 21 ) );
		$registry       = new KnowledgeSourceRegistry();
		$source_adapter = $this->createMock( KnowledgeSource::class );
		$source_adapter->method( 'type' )->willReturn( 'manual_text' );
		$source_adapter->method( 'documents' )->willReturn( array( $document ) );
		$registry->register( $source_adapter );
		$handler = new KnowledgeSourceSyncJobHandler(
			$sources,
			$registry,
			$fixture['documents'],
			new DocumentIndexJobEnqueuer( $fixture['jobs'] ),
			$this->createMock( Clock::class )
		);

		try {
			$handler->handle( $fixture['job'], $fixture['context'] );
			self::fail( 'Invalid document queue payload did not fail closed.' );
		} catch ( JobExecutionException $error ) {
			self::assertSame( 'source_sync_document_queue_invalid', $error->safe_code() );
			self::assertSame( 'WordPress content produced an invalid document queue payload.', $error->safe_message() );
			self::assertFalse( $error->retryable() );
		}
	}

	/** A standing no-argument hourly event cannot suppress the uniquely addressed immediate wake-up. */
	public function test_source_enqueue_schedules_unique_existing_job_hook_after_persistence(): void {
		if ( ! class_exists( KnowledgeSourceSyncJobEnqueuer::class ) ) {
			self::fail( 'KnowledgeSourceSyncJobEnqueuer does not exist yet.' );
		}

		$now        = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$payload    = $this->payload();
		$expected   = $this->job( $now, 'sync.source', $payload->to_array() );
		$repository = $this->createMock( JobRepository::class );
		$clock      = $this->createMock( Clock::class );

		$clock->expects( self::once() )->method( 'now' )->willReturn( $now );
		$repository->expects( self::once() )
			->method( 'enqueue' )
			->with(
				self::callback(
					static fn ( JobRequest $request ): bool => 'sync.source' === $request->type
						&& $payload->to_array() === $request->payload
						&& 1 === preg_match( '/^sync-source-[a-f0-9]{64}$/', (string) $request->idempotency_key )
				),
				$now
			)
			->willReturn( $expected );
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( string $hook, array $args = array() ) use ( $expected ): int|false {
				self::assertSame( WordPressJobCron::HOOK, $hook );
				if ( array() === $args ) {
					return 1234567890;
				}
				self::assertSame( array( $expected->job_key ), $args );
				return false;
			}
		);
		Functions\expect( 'wp_schedule_single_event' )
			->once()
			->with( $now->getTimestamp(), WordPressJobCron::HOOK, array( $expected->job_key ), true )
			->andReturn( true );

		self::assertSame( 1234567890, wp_next_scheduled( WordPressJobCron::HOOK ) );
		self::assertSame( $expected, ( new KnowledgeSourceSyncJobEnqueuer( $repository, $clock ) )->enqueue( $payload ) );
	}

	/** A durable source job remains usable when the best-effort immediate wake-up fails. */
	public function test_source_enqueue_returns_durable_job_when_worker_wake_up_fails(): void {
		$now        = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$payload    = $this->payload();
		$expected   = $this->job( $now, 'sync.source', $payload->to_array() );
		$repository = $this->createMock( JobRepository::class );
		$clock      = $this->createMock( Clock::class );

		$clock->method( 'now' )->willReturn( $now );
		$repository->method( 'enqueue' )->willReturn( $expected );
		Functions\expect( 'wp_next_scheduled' )->once()->with( WordPressJobCron::HOOK, array( $expected->job_key ) )->andReturn( false );
		Functions\expect( 'wp_schedule_single_event' )->once()->andReturn( false );

		self::assertSame( $expected, ( new KnowledgeSourceSyncJobEnqueuer( $repository, $clock ) )->enqueue( $payload ) );
	}

	/**
	 * Build a handler fixture around one optional source.
	 *
	 * @param KnowledgeSourceRecord|null $source Persisted source or null.
	 * @return array{handler:KnowledgeSourceSyncJobHandler,job:JobRecord,context:JobExecutionContext,documents:DocumentRepository&\PHPUnit\Framework\MockObject\MockObject,jobs:JobRepository&\PHPUnit\Framework\MockObject\MockObject}
	 */
	private function handler_fixture( ?KnowledgeSourceRecord $source ): array {
		$now       = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$payload   = $this->payload();
		$job       = $this->job( $now, 'sync.source', $payload->to_array() );
		$lease     = new JobLease( $job, 'worker-token' );
		$sources   = $this->createMock( KnowledgeSourceRepository::class );
		$documents = $this->createMock( DocumentRepository::class );
		$jobs      = $this->createMock( JobRepository::class );
		$clock     = $this->createMock( Clock::class );

		$sources->method( 'findById' )->willReturn( $source );
		$clock->method( 'now' )->willReturn( $now );
		$jobs->method( 'cancellationRequested' )->willReturn( false );
		$jobs->method( 'heartbeat' )->willReturn( $lease );

		$registry = new KnowledgeSourceRegistry();
		$registry->register( new ManualTextSource() );

		return array(
			'handler'   => new KnowledgeSourceSyncJobHandler( $sources, $registry, $documents, new DocumentIndexJobEnqueuer( $jobs ), $clock ),
			'job'       => $job,
			'context'   => new JobExecutionContext( $jobs, $lease, $clock, 120 ),
			'documents' => $documents,
			'jobs'      => $jobs,
		);
	}

	/**
	 * Build the approved persisted source fixture.
	 *
	 * @param DateTimeImmutable $now Fixture time.
	 */
	private function source( DateTimeImmutable $now ): KnowledgeSourceRecord {
		return new KnowledgeSourceRecord(
			7,
			'owner-guide',
			'manual_text',
			null,
			'Owner guide',
			null,
			'active',
			array(
				'text'               => 'A concise production knowledge document.',
				'semantic_retrieval' => array(
					'collection_id'         => 'wp-rag-default',
					'configuration_id'      => 'gemini-embedding-001-3072-cosine-v1',
					'embedding_provider_id' => 'gemini_direct',
					'embedding_model_id'    => 'gemini-embedding-001',
					'dimensions'            => 3072,
					'normalization'         => 'none',
					'distance'              => 'cosine',
					'vector_store_id'       => 'local-wordpress',
				),
			),
			'generation-1',
			null,
			$now,
			$now
		);
	}

	/** Build the exact approved source-sync payload. */
	private function payload(): KnowledgeSourceSyncJobPayload {
		return new KnowledgeSourceSyncJobPayload( 7, 'wp-rag-default', 'gemini-embedding-001-3072-cosine-v1', 'generation-1' );
	}

	/**
	 * Build one queue record fixture.
	 *
	 * @param DateTimeImmutable    $now Fixture time.
	 * @param string               $type Persisted job type.
	 * @param array<string, mixed> $payload Persisted payload.
	 */
	private function job( DateTimeImmutable $now, string $type, array $payload ): JobRecord {
		return new JobRecord(
			1,
			'job-0000000000000001',
			$type,
			JobStatus::RUNNING,
			'fixture-key',
			$payload,
			1,
			3,
			$now,
			'worker-token',
			$now->modify( '+120 seconds' ),
			null,
			null,
			null,
			null,
			null,
			null,
			$now,
			null,
			$now,
			$now
		);
	}
}
// phpcs:enable WordPress.NamingConventions
