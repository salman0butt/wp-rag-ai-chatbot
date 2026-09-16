<?php
/**
 * Production WordPress document-index dependency graph.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use LogicException;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\EmbeddingService;
use WpRagAiChatbot\Embeddings\IndexEmbeddingExecutor;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Indexing\Chunking\ChunkingConfig;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Chunking\LexicalTokenCounter;
use WpRagAiChatbot\Indexing\Chunking\StructureAwareChunker;
use WpRagAiChatbot\Indexing\Dedup\ChunkDeduplicator;
use WpRagAiChatbot\Indexing\DocumentIndexPipeline;
use WpRagAiChatbot\Indexing\Normalization\ContentNormalizer;
use WpRagAiChatbot\Indexing\Planning\IncrementalIndexPlanner;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Providers\ProviderRegistry;
use WpRagAiChatbot\Retrieval\Lexical\ChunkInspectionStore;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

// phpcs:disable WordPress.NamingConventions -- Existing domain DTO properties use the approved camelCase contract.
/** Resolves saved documents and executes the existing pipeline under the fixed guided profile. */
final class WordPressDocumentIndexDependencies implements DocumentIndexDependencies {
	public const COLLECTION_ID         = 'wp-rag-default';
	public const EMBEDDING_PROVIDER_ID = 'gemini_direct';
	public const EMBEDDING_MODEL_ID    = 'gemini-embedding-001';
	public const EMBEDDING_DIMENSIONS  = 3072;
	public const VECTOR_STORE_ID       = 'local-wordpress';

	/**
	 * Existing pure planning pipeline.
	 *
	 * @var DocumentIndexPipeline
	 */
	private readonly DocumentIndexPipeline $pipeline;

	/**
	 * Existing provider/vector execution boundary.
	 *
	 * @var IndexEmbeddingExecutor
	 */
	private readonly IndexEmbeddingExecutor $executor;

	/**
	 * Create the production document-index dependency graph.
	 *
	 * @param KnowledgeSourceRepository $sources Persisted source repository.
	 * @param DocumentRepository        $documents Persisted document repository.
	 * @param ProviderRegistry          $providers Registered provider authority.
	 * @param VectorStoreRegistry       $stores Registered vector-store authority.
	 * @param ChunkInspectionStore      $chunks Persisted document chunk projection.
	 * @throws LogicException When Gemini embedding support is unavailable.
	 */
	public function __construct(
		private readonly KnowledgeSourceRepository $sources,
		private readonly DocumentRepository $documents,
		ProviderRegistry $providers,
		VectorStoreRegistry $stores,
		private readonly ChunkInspectionStore $chunks
	) {
		$profile  = self::profile();
		$provider = $providers->embedding( self::EMBEDDING_PROVIDER_ID );
		if ( null === $provider ) {
			throw new LogicException( 'Configured Gemini embedding provider is not registered.' );
		}

		$collection     = new VectorCollection( self::COLLECTION_ID, $profile );
		$this->pipeline = new DocumentIndexPipeline(
			new ContentNormalizer(),
			new StructureAwareChunker(
				new LexicalTokenCounter(),
				new ChunkingConfig( 512, 64, 'm07-v1', $profile->fingerprint() )
			),
			new ChunkDeduplicator(),
			new IncrementalIndexPlanner()
		);
		$this->executor = new IndexEmbeddingExecutor(
			new EmbeddingService( $provider, new EmbeddingBatchConfig( 100 ) ),
			$stores->upsert( self::VECTOR_STORE_ID ),
			$stores->delete( self::VECTOR_STORE_ID ),
			$collection
		);
	}

	/** Return the server-owned configuration identity used by future enqueue callers. */
	public static function configuration_id(): string {
		return self::EMBEDDING_MODEL_ID . '-' . self::EMBEDDING_DIMENSIONS . '-' . DistanceMetric::COSINE->value . '-v1';
	}

	/**
	 * Return the exact persisted semantic block for the guided flow.
	 *
	 * @return array{collection_id:string,configuration_id:string,embedding_provider_id:string,embedding_model_id:string,dimensions:int,normalization:string,distance:string,vector_store_id:string}
	 */
	public static function semantic_configuration(): array {
		return array(
			'collection_id'         => self::COLLECTION_ID,
			'configuration_id'      => self::configuration_id(),
			'embedding_provider_id' => self::EMBEDDING_PROVIDER_ID,
			'embedding_model_id'    => self::EMBEDDING_MODEL_ID,
			'dimensions'            => self::EMBEDDING_DIMENSIONS,
			'normalization'         => NormalizationMode::NONE->value,
			'distance'              => DistanceMetric::COSINE->value,
			'vector_store_id'       => self::VECTOR_STORE_ID,
		);
	}

	/**
	 * Check a queued source generation against the exact current persisted configuration.
	 *
	 * @param KnowledgeSourceRecord                                 $source Persisted source.
	 * @param KnowledgeSourceSyncJobPayload|DocumentIndexJobPayload $payload Queued identifiers.
	 */
	public static function matches_source_configuration(
		KnowledgeSourceRecord $source,
		KnowledgeSourceSyncJobPayload|DocumentIndexJobPayload $payload
	): bool {
		if ( $source->id !== $payload->source_id || $source->sourceHash !== $payload->generation ) {
			return false;
		}
		if ( self::COLLECTION_ID !== $payload->collection_id || self::configuration_id() !== $payload->configuration_id ) {
			return false;
		}

		$semantic = $source->config['semantic_retrieval'] ?? null;
		$expected = self::semantic_configuration();
		if ( ! is_array( $semantic ) || count( $semantic ) !== count( $expected ) ) {
			return false;
		}
		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $semantic ) || $semantic[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve the current document and build the existing accepted index plan.
	 *
	 * @param DocumentIndexJobPayload $payload Queued document identifiers.
	 * @throws JobExecutionException When current persisted state does not match the job.
	 */
	public function plan( DocumentIndexJobPayload $payload ): IndexPlan {
		$source = $this->sources->findById( $payload->source_id );
		if ( null === $source ) {
			throw new JobExecutionException( 'index_source_not_found', 'Document indexing source is no longer available.', false );
		}
		if ( ! self::matches_source_configuration( $source, $payload ) ) {
			throw new JobExecutionException( 'index_configuration_mismatch', 'Document indexing configuration has changed.', false );
		}

		$document = $this->documents->findByKey( $payload->document_key );
		if ( null === $document ) {
			throw new JobExecutionException( 'index_document_not_found', 'Document is no longer available for indexing.', false );
		}
		if ( $document->sourceId !== $payload->source_id ) {
			throw new JobExecutionException( 'index_document_mismatch', 'Document indexing lineage is invalid.', false );
		}

		return $this->pipeline->plan( $document, $this->previous_chunks( $payload ) )->indexPlan;
	}

	/**
	 * Execute the accepted plan through the existing embedding executor.
	 *
	 * @param DocumentIndexJobPayload $payload Queued document identifiers.
	 * @param IndexPlan               $plan Accepted existing pipeline plan.
	 */
	public function execute( DocumentIndexJobPayload $payload, IndexPlan $plan ): void {
		unset( $payload );
		$this->executor->execute( $plan );
	}

	/** Build the exact approved Gemini vector compatibility profile. */
	public static function profile(): VectorIndexProfile {
		return new VectorIndexProfile(
			new EmbeddingProfile(
				self::EMBEDDING_PROVIDER_ID,
				self::EMBEDDING_MODEL_ID,
				self::EMBEDDING_DIMENSIONS,
				NormalizationMode::NONE
			),
			DistanceMetric::COSINE
		);
	}

	/**
	 * Rehydrate the bounded prior document projection for the existing incremental planner.
	 *
	 * Projection rows intentionally force current chunks through the planner's upsert path because
	 * the lexical projection does not persist every embedding compatibility field. The planner still
	 * remains the sole authority for document-scoped stale-key deletion.
	 *
	 * @param DocumentIndexJobPayload $payload Queued document identity.
	 * @return ChunkRecord[]
	 * @throws JobExecutionException When persisted prior state is invalid or exceeds execution bounds.
	 */
	private function previous_chunks( DocumentIndexJobPayload $payload ): array {
		$previous = array();
		$page     = 1;
		$total    = 0;
		$loaded   = 0;

		do {
			$result = $this->chunks->paginate_document_chunks( $payload->document_key, $page, 100 );
			$total  = $result->total;
			if ( $total > 1000 ) {
				throw new JobExecutionException( 'index_previous_chunks_unbounded', 'Document indexing state exceeds the supported chunk limit.', false );
			}
			$loaded = count( $previous );
			if ( array() === $result->items && $loaded < $total ) {
				throw new JobExecutionException( 'index_previous_chunks_invalid', 'Document indexing state could not be reconstructed.', false );
			}

			foreach ( $result->items as $record ) {
				if ( ! $record instanceof ChunkSearchRecord || $record->document_key !== $payload->document_key || $record->source_id !== $payload->source_id ) {
					throw new JobExecutionException( 'index_previous_chunks_invalid', 'Document indexing state could not be reconstructed.', false );
				}
				$previous[] = new ChunkRecord(
					$record->chunk_key,
					$record->document_key,
					$record->source_id,
					$record->document_type,
					$record->title,
					$record->canonical_url,
					$record->content,
					$record->content_hash,
					null,
					$record->content_hash,
					$record->language,
					$record->visibility,
					$record->sequence,
					null,
					array(),
					( new LexicalTokenCounter() )->count( $record->content ),
					'm07-v1',
					'lexical-projection-rehydration',
					null,
					$record->metadata
				);
			}
			$loaded = count( $previous );
			++$page;
		} while ( $loaded < $total );

		return $previous;
	}
}
// phpcs:enable WordPress.NamingConventions
