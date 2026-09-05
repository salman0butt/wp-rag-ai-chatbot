<?php
/**
 * M10 semantic retrieval adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Semantic;

use InvalidArgumentException;
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
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Filter\VectorFilterMapper;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalQuery;
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
 * Defines bounded, fail-closed semantic retrieval over the accepted M08 contracts.
 */
final class SemanticRetrieverTest extends TestCase {
	/**
	 * The normalized query is embedded once and semantic search receives the configured top-K and portable filter.
	 */
	public function test_embeds_once_and_uses_bounded_filtered_vector_search(): void {
		$provider = $this->provider();
		$store    = $this->store(
			array(
				$this->match( 'a', 0.91 ),
				$this->match( 'b', 0.81 ),
				$this->match( 'c', 0.71 ),
			)
		);
		$records  = array(
			$this->id( 'a' ) => $this->record( 'a' ),
			$this->id( 'b' ) => $this->record( 'b' ),
			$this->id( 'c' ) => $this->record( 'c' ),
		);

		$candidates = $this->retriever( $provider, $store, new RetrievalConfig( semantic_top_k: 2 ) )->retrieve(
			new RetrievalQuery( 'reset controller', array( 'reset', 'controller' ) ),
			new SemanticRetrievalContext(
				new RetrievalFilter( visibility: 'public', language: 'en' ),
				static fn ( string $chunk_id ): ?ChunkSearchRecord => $records[ $chunk_id ] ?? null
			)
		);

		self::assertCount( 1, $provider->requests );
		self::assertSame( array( 'reset controller' ), $provider->requests[0]->inputs );
		self::assertNotNull( $store->request );
		self::assertSame( 2, $store->request->top_k );
		self::assertNotNull( $store->request->filter );
		self::assertTrue(
			$store->request->filter->matches(
				array(
					'visibility' => 'public',
					'language'   => 'en',
				)
			)
		);
		self::assertFalse(
			$store->request->filter->matches(
				array(
					'visibility' => 'private',
					'language'   => 'en',
				)
			)
		);
		self::assertCount( 2, $candidates );
	}

	/**
	 * Missing or mismatched required lineage is dropped and equal native scores use stable chunk-ID ordering.
	 */
	public function test_drops_invalid_lineage_and_orders_ties_by_chunk_id(): void {
		$provider = $this->provider();
		$store    = $this->store(
			array(
				$this->match( 'b', 0.8 ),
				$this->match( 'missing-document', 0.99, array( 'document_key' => null ) ),
				$this->match( 'a', 0.8 ),
				$this->match( 'wrong-source', 0.95, array( 'source_id' => 99 ) ),
			)
		);
		$records  = array(
			$this->id( 'a' )            => $this->record( 'a' ),
			$this->id( 'b' )            => $this->record( 'b' ),
			$this->id( 'wrong-source' ) => $this->record( 'wrong-source' ),
		);

		$candidates = $this->retriever( $provider, $store, new RetrievalConfig( semantic_top_k: 4 ) )->retrieve(
			new RetrievalQuery( 'reset controller', array( 'reset', 'controller' ) ),
			new SemanticRetrievalContext(
				new RetrievalFilter( visibility: 'public' ),
				static fn ( string $chunk_id ): ?ChunkSearchRecord => $records[ $chunk_id ] ?? null
			)
		);

		$expected_ids = array( $this->id( 'a' ), $this->id( 'b' ) );
		sort( $expected_ids, SORT_STRING );
		self::assertSame( $expected_ids, array_column( $candidates, 'chunk_id' ) );
	}

	/**
	 * Unsupported mandatory filters fail before embedding or vector search can broaden access.
	 */
	public function test_unsupported_mandatory_filter_fails_before_external_work(): void {
		$provider = $this->provider();
		$store    = $this->store( array() );
		$context  = new SemanticRetrievalContext(
			new RetrievalFilter( mandatory: array( 'tenant_id' => 'tenant-a' ) ),
			static fn ( string $chunk_id ): ?ChunkSearchRecord => '' === $chunk_id ? null : null
		);

		try {
			$this->retriever( $provider, $store )->retrieve(
				new RetrievalQuery( 'reset controller', array( 'reset', 'controller' ) ),
				$context
			);
			self::fail( 'Expected unsupported mandatory filter failure.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertStringContainsString( 'Unsupported mandatory retrieval filter', $exception->getMessage() );
		}

		self::assertCount( 0, $provider->requests );
		self::assertSame( 0, $store->search_count );
	}

	/**
	 * Build a semantic retriever with deterministic M08 dependencies.
	 *
	 * @param RecordingEmbeddingProvider $provider Recording embedding provider.
	 * @param VectorSearchStore          $store    Recording vector search store.
	 * @param RetrievalConfig|null       $config   Optional retrieval configuration.
	 */
	private function retriever(
		RecordingEmbeddingProvider $provider,
		VectorSearchStore $store,
		?RetrievalConfig $config = null
	): SemanticRetriever {
		$profile    = new EmbeddingProfile( 'test-embedding', 'embed-model', 2, NormalizationMode::NONE );
		$collection = new VectorCollection( 'site-1', new VectorIndexProfile( $profile, DistanceMetric::COSINE ) );

		return new SemanticRetriever(
			new EmbeddingService( $provider, new EmbeddingBatchConfig( 8 ) ),
			$profile,
			$collection,
			$store,
			new VectorFilterMapper(),
			$config ?? new RetrievalConfig()
		);
	}

	/**
	 * Build a deterministic one-query embedding provider.
	 */
	private function provider(): RecordingEmbeddingProvider {
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

	/**
	 * Build a recording semantic store.
	 *
	 * @param VectorMatch[] $matches Returned matches before top-K enforcement.
	 */
	private function store( array $matches ): VectorSearchStore {
		return new class($matches) implements VectorSearchStore {
			/**
			 * Captured vector search request.
			 *
			 * @var VectorSearchRequest|null
			 */
			public ?VectorSearchRequest $request = null;

			/**
			 * Number of vector search calls.
			 *
			 * @var int
			 */
			public int $search_count = 0;

			/**
			 * Create the recording store.
			 *
			 * @param VectorMatch[] $matches Deterministic returned matches.
			 */
			public function __construct( private readonly array $matches ) {
			}

			/** Return the stable test store ID. */
			public function store_id(): string {
				return 'recording-semantic';
			}

			/** Return truthful test capabilities. */
			public function capabilities(): VectorStoreCapabilities {
				return VectorStoreCapabilities::all();
			}

			/** Return healthy test state. */
			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}

			/**
			 * Capture one bounded semantic request.
			 *
			 * @param VectorSearchRequest $request Semantic vector search request.
			 */
			public function search( VectorSearchRequest $request ): VectorSearchResult {
				$this->request = $request;
				++$this->search_count;
				return new VectorSearchResult( array_slice( $this->matches, 0, $request->top_k ) );
			}
		};
	}

	/**
	 * Build one vector match using the actual M08 persisted metadata keys.
	 *
	 * @param string $seed     Stable fixture seed.
	 * @param float  $score    Native vector score.
	 * @param array  $override Metadata overrides; null removes a key.
	 * @phpstan-param array<string, scalar|null> $override
	 */
	private function match( string $seed, float $score, array $override = array() ): VectorMatch {
		$metadata = array(
			'document_key' => 'doc-' . $seed,
			'source_id'    => 7,
			'language'     => 'en',
			'visibility'   => 'public',
		);
		foreach ( $override as $key => $value ) {
			if ( null === $value ) {
				unset( $metadata[ $key ] );
			} else {
				$metadata[ $key ] = $value;
			}
		}

		return new VectorMatch( $this->id( $seed ), $score, $metadata );
	}

	/**
	 * Build one canonical local search-projection record used to hydrate semantic content.
	 *
	 * @param string $seed Stable fixture seed.
	 */
	private function record( string $seed ): ChunkSearchRecord {
		$content = 'Canonical content for ' . $seed;
		return new ChunkSearchRecord(
			$this->id( $seed ),
			'doc-' . $seed,
			7,
			'post',
			'Example',
			null,
			$content,
			hash( 'sha256', $content ),
			'en',
			'public',
			0
		);
	}

	/**
	 * Build a stable portable chunk ID.
	 *
	 * @param string $seed Stable fixture seed.
	 */
	private function id( string $seed ): string {
		return hash( 'sha256', $seed );
	}
}
