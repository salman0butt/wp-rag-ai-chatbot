<?php
/**
 * M10 end-to-end hybrid retrieval acceptance fixture.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\Retrieval;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\EmbeddingService;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Retrieval\Access\DefaultCandidateAccessPolicy;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Filter\VectorFilterMapper;
use WpRagAiChatbot\Retrieval\Fusion\ReciprocalRankFusion;
use WpRagAiChatbot\Retrieval\HybridRetriever;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchStore;
use WpRagAiChatbot\Retrieval\Lexical\LexicalFilter;
use WpRagAiChatbot\Retrieval\Lexical\LexicalRetriever;
use WpRagAiChatbot\Retrieval\Lexical\LexicalScorer;
use WpRagAiChatbot\Retrieval\Lexical\LexicalSearchMatch;
use WpRagAiChatbot\Retrieval\Lexical\LexicalSearchRequest;
use WpRagAiChatbot\Retrieval\QueryPreprocessor;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetrievalContext;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetriever;
use WpRagAiChatbot\Tests\Support\Embeddings\RecordingEmbeddingProvider;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorMatch;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorSearchResult;
use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;

/**
 * Proves the accepted local lexical/semantic adapters compose into bounded safe hybrid retrieval.
 */
final class HybridRetrievalAcceptanceTest extends TestCase {
	/**
	 * Exact identifiers and paraphrases survive their respective channels without leaking private chunks.
	 */
	public function test_local_hybrid_path_is_deterministic_bounded_and_fail_closed(): void {
		$config     = new RetrievalConfig(
			semantic_top_k: 3,
			lexical_candidate_limit: 3,
			fused_candidate_limit: 3,
			context_candidate_limit: 2
		);
		$exact      = $this->record( 'exact', 'Install SKU-42/A by locking the controller bracket.' );
		$paraphrase = $this->record( 'paraphrase', 'Mount the control unit by aligning the rear bracket.' );
		$restricted = $this->record( 'private', 'SKU-42/A confidential service calibration.', 'private' );
		$records    = array(
			$exact->chunk_key      => $exact,
			$paraphrase->chunk_key => $paraphrase,
			$restricted->chunk_key => $restricted,
		);
		$lexical    = new LexicalRetriever(
			$this->lexical_store( $exact, $paraphrase, $restricted ),
			new LexicalScorer(),
			$config
		);
		$semantic   = new SemanticRetriever(
			new EmbeddingService( $this->embedding_provider(), new EmbeddingBatchConfig( 8 ) ),
			$this->embedding_profile(),
			$this->vector_collection(),
			$this->vector_store( $paraphrase, $exact, $restricted ),
			new VectorFilterMapper(),
			$config
		);
		$retriever  = new HybridRetriever(
			$semantic,
			$lexical,
			new ReciprocalRankFusion( $config ),
			new ConfidenceEstimator(),
			new DefaultCandidateAccessPolicy(),
			$config
		);
		$query      = ( new QueryPreprocessor( $config ) )->preprocess( 'How do I fit SKU-42/A to the controller?' );
		$filter     = new RetrievalFilter( visibility: 'public', language: 'en', source_ids: array( 7 ) );
		$result     = $retriever->retrieve(
			$query,
			new SemanticRetrievalContext(
				$filter,
				static fn ( string $chunk_id ): ?ChunkSearchRecord => $records[ $chunk_id ] ?? null
			),
			new LexicalFilter( 'knowledge', null, 7, 'en', 'public' )
		);

		self::assertCount( 2, $result->candidates );
		self::assertNotContains( $restricted->chunk_key, array_column( $result->candidates, 'chunk_id' ) );
		self::assertSame( array( 'public' ), array_values( array_unique( array_column( $result->candidates, 'visibility' ) ) ) );

		$by_id = array_column( $result->candidates, null, 'chunk_id' );
		self::assertArrayHasKey( $exact->chunk_key, $by_id );
		self::assertArrayHasKey( $paraphrase->chunk_key, $by_id );
		self::assertContains( 'lexical', array_column( $by_id[ $exact->chunk_key ]->channel_evidence, 'channel' ) );
		self::assertContains( 'semantic', array_column( $by_id[ $paraphrase->chunk_key ]->channel_evidence, 'channel' ) );

		$expected_order = array( $exact->chunk_key, $paraphrase->chunk_key );
		sort( $expected_order, SORT_STRING );
		self::assertSame( $expected_order, array_column( $result->candidates, 'chunk_id' ) );
		self::assertSame( hash( 'sha256', $query->normalized ), $result->trace->query_hash );
		self::assertSame( strlen( $query->normalized ), $result->trace->query_bytes );
		self::assertSame( array(), $result->trace->channel_failures );
		self::assertSame( 'disabled', $result->trace->rerank_status );
		self::assertLessThanOrEqual( $config->context_candidate_limit, count( $result->candidates ) );
	}

	/**
	 * Build one canonical local projection record.
	 *
	 * @param string $seed Stable fixture seed.
	 * @param string $content Canonical chunk content.
	 * @param string $visibility Trusted visibility.
	 */
	private function record( string $seed, string $content, string $visibility = 'public' ): ChunkSearchRecord {
		return new ChunkSearchRecord(
			hash( 'sha256', $seed ),
			'doc-' . $seed,
			7,
			'post',
			'Controller guide',
			null,
			$content,
			hash( 'sha256', $content ),
			'en',
			$visibility,
			0
		);
	}

	/**
	 * Build a deterministic bounded lexical projection adapter.
	 *
	 * @param ChunkSearchRecord ...$records Canonical records.
	 */
	private function lexical_store( ChunkSearchRecord ...$records ): ChunkSearchStore {
		return new class($records) implements ChunkSearchStore {
			/**
			 * Create the bounded lexical fixture store.
			 *
			 * @param array $records Fixture records.
			 * @phpstan-param list<ChunkSearchRecord> $records
			 */
			public function __construct( private readonly array $records ) {
			}

			/**
			 * Ignore replacement in the read-only acceptance fixture.
			 *
			 * @param string            $collection_id Collection scope.
			 * @param string            $document_key Document scope.
			 * @param ChunkSearchRecord ...$chunks Replacement rows.
			 */
			public function replace_document_chunks( string $collection_id, string $document_key, ChunkSearchRecord ...$chunks ): void {
			}

			/**
			 * Ignore deletion in the read-only acceptance fixture.
			 *
			 * @param string $collection_id Collection scope.
			 * @param string $document_key Document scope.
			 */
			public function delete_document( string $collection_id, string $document_key ): void {
			}

			/**
			 * Return only the request-bounded deterministic fixture prefix.
			 *
			 * @param LexicalSearchRequest $request Bounded lexical request.
			 * @return list<LexicalSearchMatch>
			 */
			public function search( LexicalSearchRequest $request ): array {
				return array_map(
					static fn ( ChunkSearchRecord $record ): LexicalSearchMatch => new LexicalSearchMatch( $record ),
					array_slice( $this->records, 0, $request->limit )
				);
			}
		};
	}

	/** Build the deterministic fake embedding provider used by the real semantic adapter. */
	private function embedding_provider(): RecordingEmbeddingProvider {
		return new RecordingEmbeddingProvider(
			array(
				new EmbeddingResult(
					'test-embedding',
					'embed-model',
					array( new EmbeddingVector( 0, array( 1.0, 0.0 ) ) ),
					EmbeddingUsage::unknown()
				),
			)
		);
	}

	/** Build the accepted deterministic embedding profile. */
	private function embedding_profile(): EmbeddingProfile {
		return new EmbeddingProfile( 'test-embedding', 'embed-model', 2, NormalizationMode::NONE );
	}

	/** Build the vector collection matching the deterministic embedding profile. */
	private function vector_collection(): VectorCollection {
		return new VectorCollection(
			'site-1',
			new VectorIndexProfile( $this->embedding_profile(), DistanceMetric::COSINE )
		);
	}

	/**
	 * Build a deterministic bounded vector search adapter.
	 *
	 * @param ChunkSearchRecord $paraphrase Semantic-first public fixture.
	 * @param ChunkSearchRecord $exact Shared exact fixture.
	 * @param ChunkSearchRecord $restricted Restricted high-score fixture.
	 */
	private function vector_store(
		ChunkSearchRecord $paraphrase,
		ChunkSearchRecord $exact,
		ChunkSearchRecord $restricted
	): VectorSearchStore {
		$matches = array(
			$this->vector_match( $restricted, 0.99 ),
			$this->vector_match( $paraphrase, 0.95 ),
			$this->vector_match( $exact, 0.80 ),
		);

		return new class($matches) implements VectorSearchStore {
			/**
			 * Create the deterministic vector fixture store.
			 *
			 * @param array $matches Deterministic matches.
			 * @phpstan-param list<VectorMatch> $matches
			 */
			public function __construct( private readonly array $matches ) {
			}

			/** Return stable local test-store identifier. */
			public function store_id(): string {
				return 'acceptance-vector';
			}

			/** Return supported portable capabilities. */
			public function capabilities(): VectorStoreCapabilities {
				return VectorStoreCapabilities::all();
			}

			/** Return healthy deterministic status. */
			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}

			/**
			 * Execute one bounded deterministic search.
			 *
			 * @param VectorSearchRequest $request Bounded vector request.
			 */
			public function search( VectorSearchRequest $request ): VectorSearchResult {
				return new VectorSearchResult( array_slice( $this->matches, 0, $request->top_k ) );
			}
		};
	}

	/**
	 * Build one portable vector match from canonical trusted lineage.
	 *
	 * @param ChunkSearchRecord $record Canonical chunk record.
	 * @param float             $score Deterministic native score.
	 */
	private function vector_match( ChunkSearchRecord $record, float $score ): VectorMatch {
		return new VectorMatch(
			$record->chunk_key,
			$score,
			array(
				'document_key' => $record->document_key,
				'source_id'    => $record->source_id,
				'language'     => $record->language,
				'visibility'   => $record->visibility,
			)
		);
	}
}
