<?php
/**
 * M09 queued document synchronization integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\Jobs\Sync;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\EmbeddingService;
use WpRagAiChatbot\Embeddings\IndexEmbeddingExecutor;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Chunking\ChunkingConfig;
use WpRagAiChatbot\Indexing\Chunking\LexicalTokenCounter;
use WpRagAiChatbot\Indexing\Chunking\StructureAwareChunker;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicator;
use WpRagAiChatbot\Indexing\DocumentIndexPipeline;
use WpRagAiChatbot\Indexing\Normalization\ContentNormalizer;
use WpRagAiChatbot\Indexing\Planning\IncrementalIndexPlanner;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobHandler;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Tests\Support\Embeddings\RecordingEmbeddingProvider;
use WpRagAiChatbot\Tests\Support\VectorStore\InMemoryVectorStore;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;

// phpcs:disable WordPress.NamingConventions -- Assertions use approved M07/M08 camelCase domain contracts.
// phpcs:disable Generic.Formatting.MultipleStatementAlignment -- Integration fixtures favor local grouping over alignment churn.
/**
 * Verifies queued orchestration reuses the accepted M07 and M08 execution boundaries.
 */
final class DocumentIndexJobHandlerIntegrationTest extends TestCase {
	/**
	 * A queued identifier payload executes real M07/M08 work and safely reruns by stable identities.
	 */
	public function test_handler_runs_real_m07_plan_through_m08_executor_and_reruns_idempotently(): void {
		$now = new DateTimeImmutable( '2026-09-05T10:00:00+00:00' );
		$profile = new VectorIndexProfile(
			new EmbeddingProfile( 'test-embedding', 'embed-model', 2, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);
		$document = new DocumentRecord(
			null,
			DocumentHasher::hash( array( 'document' => 'queued-doc' ) ),
			42,
			'queued-doc',
			'post',
			'Queued document',
			'https://example.test/queued-doc',
			'# Heading' . "\n\n" . 'A short public document is reconstructed server-side for queued indexing.',
			array( 'post_id' => 42 ),
			'generation-7',
			DocumentHasher::hash( array( 'content' => 'queued-doc-content' ) ),
			'en',
			'public',
			$now,
			$now
		);
		$pipeline = new DocumentIndexPipeline(
			new ContentNormalizer(),
			new StructureAwareChunker( new LexicalTokenCounter(), new ChunkingConfig( 64, 8 ) ),
			new ChunkDeduplicator(),
			new IncrementalIndexPlanner()
		);
		$planned = $pipeline->plan( $document );
		self::assertCount( 1, $planned->indexPlan->upsert );

		$embedding_result = new EmbeddingResult(
			'test-embedding',
			'embed-model',
			array( new EmbeddingVector( 0, array( 1.0, 0.0 ) ) ),
			EmbeddingUsage::input_tokens( 8 )
		);
		$provider = new RecordingEmbeddingProvider( array( $embedding_result, $embedding_result ) );
		$store = new InMemoryVectorStore( 'memory' );
		$collection = new VectorCollection( 'collection-main', $profile );
		$executor = new IndexEmbeddingExecutor(
			new EmbeddingService( $provider, new EmbeddingBatchConfig( 10 ) ),
			$store,
			$store,
			$collection
		);
		$payload = new DocumentIndexJobPayload(
			$document->documentKey,
			$document->sourceId,
			'collection-main',
			'index-profile-default',
			'generation-7'
		);
		$dependencies = new class( $pipeline, $document, $executor, $profile->fingerprint() ) implements DocumentIndexDependencies {
			/**
			 * Create one offline reconstruction fixture.
			 *
			 * @param DocumentIndexPipeline  $pipeline Real M07 planning pipeline.
			 * @param DocumentRecord         $document Reconstructed current document fixture.
			 * @param IndexEmbeddingExecutor $executor Real M08 execution boundary.
			 * @param string                 $compatibility_key Selected embedding/index compatibility fingerprint.
			 */
			public function __construct(
				private readonly DocumentIndexPipeline $pipeline,
				private readonly DocumentRecord $document,
				private readonly IndexEmbeddingExecutor $executor,
				private readonly string $compatibility_key
			) {
			}

			/**
			 * Reconstruct the current document and stamp the selected server-side embedding compatibility.
			 *
			 * @param DocumentIndexJobPayload $payload Identifier-only persisted job payload.
			 * @throws RuntimeException When the payload does not resolve the expected fixture document.
			 */
			public function plan( DocumentIndexJobPayload $payload ): IndexPlan {
				if ( $payload->document_key !== $this->document->documentKey || $payload->source_id !== $this->document->sourceId ) {
					throw new RuntimeException( 'Fixture payload did not resolve the expected document.' );
				}
				$raw = $this->pipeline->plan( $this->document )->indexPlan;
				$upsert = array_map( fn ( ChunkRecord $chunk ): ChunkRecord => $this->compatible_chunk( $chunk ), $raw->upsert );
				return new IndexPlan( $upsert, $raw->metadataRefresh, $raw->deleteKeys, $raw->unchanged, $raw->duplicateAliases );
			}

			/**
			 * Delegate the accepted plan to the real M08 executor.
			 *
			 * @param DocumentIndexJobPayload $payload Identifier-only persisted job payload.
			 * @param IndexPlan               $plan Accepted M07 plan.
			 */
			public function execute( DocumentIndexJobPayload $payload, IndexPlan $plan ): void {
				unset( $payload );
				$this->executor->execute( $plan );
			}

			/**
			 * Return the same deterministic M07 chunk under the selected M08 compatibility profile.
			 *
			 * @param ChunkRecord $chunk Planned M07 chunk.
			 */
			private function compatible_chunk( ChunkRecord $chunk ): ChunkRecord {
				return new ChunkRecord(
					$chunk->chunkKey,
					$chunk->documentKey,
					$chunk->sourceId,
					$chunk->documentType,
					$chunk->title,
					$chunk->canonicalUrl,
					$chunk->content,
					$chunk->contentHash,
					$chunk->sourceVersion,
					$chunk->documentContentHash,
					$chunk->language,
					$chunk->visibility,
					$chunk->sequence,
					$chunk->parentChunkKey,
					$chunk->headingPath,
					$chunk->tokenCount,
					$chunk->chunkingVersion,
					$chunk->chunkingFingerprint,
					$this->compatibility_key,
					$chunk->sourceMetadata
				);
			}
		};

		$job = $this->job( $now, $payload->to_array() );
		$lease = new JobLease( $job, 'worker-token' );
		$repository = $this->createMock( JobRepository::class );
		$clock = $this->createMock( Clock::class );
		$clock->method( 'now' )->willReturn( $now );
		$repository->method( 'cancellationRequested' )->willReturn( false );
		$repository->method( 'heartbeat' )->willReturn( $lease );

		$handler = new DocumentIndexJobHandler( $dependencies );
		$context = new JobExecutionContext( $repository, $lease, $clock, 120 );
		$handler->handle( $job, $context );

		self::assertCount( 1, $provider->requests );
		$first_result = $store->search( new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $profile->fingerprint() ) );
		self::assertCount( 1, $first_result->matches );
		self::assertSame( $planned->indexPlan->upsert[0]->chunkKey, $first_result->matches[0]->id );

		$handler->handle( $job, $context );

		self::assertCount( 2, $provider->requests );
		$rerun_result = $store->search( new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $profile->fingerprint() ) );
		self::assertCount( 1, $rerun_result->matches );
		self::assertSame( $first_result->matches[0]->id, $rerun_result->matches[0]->id );
	}

	/**
	 * Build one running synchronization job.
	 *
	 * @param DateTimeImmutable    $now Fixture time.
	 * @param array<string, mixed> $payload Persisted identifier payload.
	 */
	private function job( DateTimeImmutable $now, array $payload ): JobRecord {
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
// phpcs:enable Generic.Formatting.MultipleStatementAlignment
// phpcs:enable WordPress.NamingConventions
