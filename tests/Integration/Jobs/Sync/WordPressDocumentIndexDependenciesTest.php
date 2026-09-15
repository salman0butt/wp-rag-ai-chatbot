<?php
/**
 * Production WordPress document-index dependency integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\Jobs\Sync;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Documents\DocumentHasher;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;
use WpRagAiChatbot\Jobs\Sync\SearchProjectionDocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Sync\WordPressDocumentIndexDependencies;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\ProviderRegistry;
use WpRagAiChatbot\Retrieval\Lexical\ChunkInspectionStore;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\WpdbChunkSearchStore;
use WpRagAiChatbot\Tests\Support\VectorStore\InMemoryVectorStore;
use WpRagAiChatbot\Tests\Support\VectorStore\ScriptedLocalVectorConnection;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStore;
use WpRagAiChatbot\VectorStore\Local\LocalVectorStoreConfig;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

// phpcs:disable WordPress.NamingConventions -- Assertions use the approved domain DTO properties.
/**
 * Proves the production dependency graph reaches both local index projections.
 */
final class WordPressDocumentIndexDependenciesTest extends TestCase {
	/** Provide the WordPress JSON helper used by production stores. */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_json_encode' )->alias(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test shim for the WordPress helper.
			static fn ( mixed $value ): string|false => json_encode( $value )
		);
	}

	/** Stop WordPress function isolation. */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/** A saved manual document uses Gemini-compatible chunks and creates vector plus lexical rows. */
	public function test_indexed_manual_document_creates_vector_and_lexical_projections(): void {
		if ( ! class_exists( WordPressDocumentIndexDependencies::class ) ) {
			self::fail( 'WordPressDocumentIndexDependencies does not exist yet.' );
		}

		$now        = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$source     = $this->source( $now );
		$document   = $this->document( $now );
		$sources    = $this->createMock( KnowledgeSourceRepository::class );
		$documents  = $this->createMock( DocumentRepository::class );
		$provider   = $this->provider();
		$providers  = new ProviderRegistry();
		$connection = new ScriptedLocalVectorConnection();
		$tables     = new TableNames( 'wp_' );
		$stores     = new VectorStoreRegistry();

		$sources->method( 'findById' )->with( 7 )->willReturn( $source );
		$documents->method( 'findByKey' )->with( 'manual:owner-guide' )->willReturn( $document );
		$providers->register( 'gemini_direct', $provider, null, $provider );
		$stores->register( new LocalVectorStore( $connection, $tables, new LocalVectorStoreConfig( 100, 20 ) ) );
		$connection->row_results = array( null, null );

		$chunks       = new WpdbChunkSearchStore( $connection, $tables );
		$dependencies = new SearchProjectionDocumentIndexDependencies(
			new WordPressDocumentIndexDependencies( $sources, $documents, $providers, $stores, $chunks ),
			$chunks
		);
		$payload      = new DocumentIndexJobPayload(
			'manual:owner-guide',
			7,
			'wp-rag-default',
			'gemini-embedding-001-3072-cosine-v1',
			'generation-1'
		);

		$plan = $dependencies->plan( $payload );
		self::assertCount( 1, $plan->upsert );
		self::assertSame(
			hash( 'sha256', "v1\nprovider=gemini_direct\nmodel=gemini-embedding-001\ndimensions=3072\nnormalization=none\ndistance=cosine" ),
			$plan->upsert[0]->embeddingCompatibilityKey
		);

		$dependencies->execute( $payload, $plan );

		self::assertCount( 1, $provider->requests );
		self::assertSame( 'gemini-embedding-001', $provider->requests[0]->model );
		self::assertSame( 3072, $provider->requests[0]->dimensions );
		self::assertSame(
			array( 'wp_rag_ai_vector_collections', 'wp_rag_ai_vectors', 'wp_rag_ai_chunk_search' ),
			array_column( $connection->inserts, 'table' )
		);
	}

	/** A document job cannot run after its persisted source profile or generation changes. */
	public function test_stale_document_payload_is_rejected_before_provider_work(): void {
		if ( ! class_exists( WordPressDocumentIndexDependencies::class ) ) {
			self::fail( 'WordPressDocumentIndexDependencies does not exist yet.' );
		}

		$now        = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$sources    = $this->createMock( KnowledgeSourceRepository::class );
		$documents  = $this->createMock( DocumentRepository::class );
		$provider   = $this->provider();
		$providers  = new ProviderRegistry();
		$stores     = new VectorStoreRegistry();
		$connection = new ScriptedLocalVectorConnection();

		$sources->method( 'findById' )->willReturn( $this->source( $now ) );
		$documents->expects( self::never() )->method( 'findByKey' );
		$providers->register( 'gemini_direct', $provider, null, $provider );
		$stores->register( new LocalVectorStore( $connection, new TableNames( 'wp_' ), new LocalVectorStoreConfig( 100, 20 ) ) );

		$dependencies = new WordPressDocumentIndexDependencies(
			$sources,
			$documents,
			$providers,
			$stores,
			new WpdbChunkSearchStore( $connection, new TableNames( 'wp_' ) )
		);

		try {
			$dependencies->plan(
				new DocumentIndexJobPayload(
					'manual:owner-guide',
					7,
					'wp-rag-default',
					'gemini-embedding-001-3072-cosine-v1',
					'stale-generation'
				)
			);
			self::fail( 'Stale document-index payload was accepted.' );
		} catch ( JobExecutionException $error ) {
			self::assertSame( 'index_configuration_mismatch', $error->safe_code() );
			self::assertFalse( $error->retryable() );
			self::assertCount( 0, $provider->requests );
		}
	}

	/** Re-indexing a smaller document removes vector rows for chunks no longer present. */
	public function test_shrinking_document_removes_stale_vectors(): void {
		$now       = new DateTimeImmutable( '2026-09-15T10:00:00+00:00' );
		$source    = $this->source( $now );
		$document  = $this->document( $now, implode( ' ', array_fill( 0, 700, 'large' ) ) );
		$prior     = array();
		$sources   = $this->createMock( KnowledgeSourceRepository::class );
		$documents = $this->createMock( DocumentRepository::class );
		$chunks    = $this->createMock( ChunkInspectionStore::class );
		$providers = new ProviderRegistry();
		$stores    = new VectorStoreRegistry();
		$vectors   = new InMemoryVectorStore( 'local-wordpress' );
		$provider  = $this->provider();
		$payload   = new DocumentIndexJobPayload(
			'manual:owner-guide',
			7,
			'wp-rag-default',
			'gemini-embedding-001-3072-cosine-v1',
			'generation-1'
		);

		$sources->method( 'findById' )->willReturn( $source );
		$documents->method( 'findByKey' )->willReturnCallback(
			static function () use ( &$document ): DocumentRecord {
				return $document;
			}
		);
		$chunks->method( 'paginate_document_chunks' )->willReturnCallback(
			static function ( string $document_key, int $page, int $per_page ) use ( &$prior ): PagedResult {
				return new PagedResult(
					'manual:owner-guide' === $document_key && 1 === $page ? $prior : array(),
					count( $prior ),
					$page,
					$per_page
				);
			}
		);
		$providers->register( 'gemini_direct', $provider, null, $provider );
		$stores->register( $vectors );
		$dependencies = new WordPressDocumentIndexDependencies( $sources, $documents, $providers, $stores, $chunks );

		$first = $dependencies->plan( $payload );
		self::assertGreaterThan( 1, count( $first->upsert ) );
		$dependencies->execute( $payload, $first );
		$prior    = array_map(
			static fn ( $chunk ): ChunkSearchRecord => new ChunkSearchRecord(
				$chunk->chunkKey,
				$chunk->documentKey,
				$chunk->sourceId,
				$chunk->documentType,
				$chunk->title,
				$chunk->canonicalUrl,
				$chunk->content,
				$chunk->contentHash,
				$chunk->language,
				$chunk->visibility,
				$chunk->sequence,
				$chunk->sourceMetadata
			),
			$first->upsert
		);
		$document = $this->document( $now, 'Short current guidance.' );
		$second   = $dependencies->plan( $payload );
		self::assertNotEmpty( $second->deleteKeys );
		$dependencies->execute( $payload, $second );

		$profile      = new VectorIndexProfile(
			new EmbeddingProfile( 'gemini_direct', 'gemini-embedding-001', 3072, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);
		$result       = $vectors->search(
			new VectorSearchRequest(
				new VectorCollection( 'wp-rag-default', $profile ),
				array_fill( 0, 3072, 0.125 ),
				100,
				$profile->fingerprint()
			)
		);
		$current_keys = array_column( $result->matches, 'id' );
		self::assertCount( 1, $current_keys );
		self::assertEmpty( array_intersect( $second->deleteKeys, $current_keys ) );
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
				'text'               => 'Production owner guidance.',
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

	/**
	 * Build one saved manual document.
	 *
	 * @param DateTimeImmutable $now Fixture time.
	 * @param string|null       $content Optional document content.
	 */
	private function document( DateTimeImmutable $now, ?string $content = null ): DocumentRecord {
		$content ??= '# Owner guide' . "\n\n" . 'Production owner guidance for the approved knowledge flow.';

		return new DocumentRecord(
			11,
			'manual:owner-guide',
			7,
			null,
			'manual_text',
			'Owner guide',
			null,
			$content,
			array( 'source_type' => 'manual_text' ),
			'generation-1',
			DocumentHasher::hash( array( 'content' => $content ) ),
			'en',
			'public',
			$now,
			$now
		);
	}

	/**
	 * Build one Gemini-identified recording provider without real network work.
	 *
	 * @return GenerationProvider&EmbeddingProvider&object{requests:list<EmbeddingRequest>}
	 */
	private function provider(): GenerationProvider&EmbeddingProvider {
		return new class() implements GenerationProvider, EmbeddingProvider {
			/**
			 * Recorded embedding requests.
			 *
			 * @var list<EmbeddingRequest>
			 */
			public array $requests = array();

			/** Return the production Gemini provider identity. */
			public function provider_id(): string {
				return 'gemini_direct';
			}

			/** Report the deterministic fixture as available. */
			public function available(): bool {
				return true;
			}

			/**
			 * Reject generation because this fixture serves embedding work only.
			 *
			 * @param GenerationRequest $request Unused generation request.
			 * @throws LogicException Always; generation is outside this test.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				unset( $request );
				throw new LogicException( 'Generation is outside this test.' );
			}

			/**
			 * Return one deterministic Gemini-shaped vector.
			 *
			 * @param EmbeddingRequest $request Recorded embedding request.
			 */
			public function embed( EmbeddingRequest $request ): EmbeddingResult {
				$this->requests[] = $request;
				$vectors          = array();
				foreach ( $request->inputs as $index => $input ) {
					unset( $input );
					$vectors[] = new EmbeddingVector( $index, array_fill( 0, 3072, 0.125 ) );
				}

				return new EmbeddingResult(
					'gemini_direct',
					'gemini-embedding-001',
					$vectors,
					EmbeddingUsage::input_tokens( 8 )
				);
			}
		};
	}
}
// phpcs:enable WordPress.NamingConventions
