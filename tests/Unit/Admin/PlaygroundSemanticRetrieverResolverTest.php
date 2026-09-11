<?php
/**
 * M13 Playground semantic retriever composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundEmbeddingProviderResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticRetrieverResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundVectorCollectionResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundVectorStoreResolver;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Providers\EmbeddingProvider;
use WpRagAiChatbot\Providers\EmbeddingRequest;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\GenerationProvider;
use WpRagAiChatbot\Providers\GenerationRequest;
use WpRagAiChatbot\Providers\GenerationResult;
use WpRagAiChatbot\Providers\ProviderRegistry;
use WpRagAiChatbot\Retrieval\Filter\VectorFilterMapper;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\Semantic\SemanticRetriever;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorSearchResult;
use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

/**
 * Proves persisted Playground runtime identity composes the existing production semantic retriever.
 */
final class PlaygroundSemanticRetrieverResolverTest extends TestCase {
	/** Composition must reuse production provider, collection, store, filtering, and retrieval contracts. */
	public function test_composes_production_semantic_retriever_from_persisted_identity(): void {
		$providers = new ProviderRegistry();
		$providers->register(
			'openai-direct',
			$this->generation_provider( 'openai-direct' ),
			null,
			$this->embedding_provider( 'openai-direct' )
		);
		$stores = new VectorStoreRegistry();
		$stores->register( $this->search_store( 'local-wordpress' ) );

		$resolver = new PlaygroundSemanticRetrieverResolver(
			new PlaygroundEmbeddingProviderResolver( $providers ),
			new PlaygroundVectorCollectionResolver(),
			new PlaygroundVectorStoreResolver( $stores ),
			new EmbeddingBatchConfig( 1 ),
			new VectorFilterMapper(),
			new RetrievalConfig(
				4096,
				128,
				20,
				100,
				40,
				20,
				12,
				60,
				1.0,
				1.0,
				true
			)
		);

		self::assertInstanceOf(
			SemanticRetriever::class,
			$resolver->resolve( $this->retrieval(), $this->semantic() )
		);
	}

	/** Build the persisted retrieval selection used by the composition fixture. */
	private function retrieval(): PlaygroundRetrievalConfiguration {
		$now = new DateTimeImmutable( '2026-09-11T10:00:00+00:00' );
		return new PlaygroundRetrievalConfiguration(
			new KnowledgeSourceRecord(
				7,
				'wordpress-posts',
				'wordpress_posts',
				null,
				'WordPress Posts',
				null,
				'indexed',
				array(),
				null,
				$now,
				$now,
				$now
			),
			'production-rag'
		);
	}

	/** Build the persisted semantic identity used by the composition fixture. */
	private function semantic(): PlaygroundSemanticConfiguration {
		return new PlaygroundSemanticConfiguration(
			new EmbeddingProfile( 'openai-direct', 'text-embedding-test', 3, NormalizationMode::NONE ),
			DistanceMetric::COSINE,
			'local-wordpress'
		);
	}

	/**
	 * Build a deterministic generation-provider registry fixture.
	 *
	 * @param string $provider_id Provider ID exposed by the fixture.
	 */
	private function generation_provider( string $provider_id ): GenerationProvider {
		return new class( $provider_id ) implements GenerationProvider {
			/**
			 * Create the deterministic generation fixture.
			 *
			 * @param string $id Provider ID exposed by the fixture.
			 */
			public function __construct( private readonly string $id ) {
			}

			/** Return the configured provider ID. */
			public function provider_id(): string {
				return $this->id;
			}

			/** Test fixtures are available without network work. */
			public function available(): bool {
				return true;
			}

			/**
			 * Generation is outside this composition contract.
			 *
			 * @param GenerationRequest $request Generation request that must never execute here.
			 * @throws LogicException Always, because generation is outside composition scope.
			 */
			public function generate( GenerationRequest $request ): GenerationResult {
				throw new LogicException( 'Generation must not run while composing semantic retrieval.' );
			}
		};
	}

	/**
	 * Build a deterministic embedding-provider registry fixture.
	 *
	 * @param string $provider_id Provider ID exposed by the fixture.
	 */
	private function embedding_provider( string $provider_id ): EmbeddingProvider {
		return new class( $provider_id ) implements EmbeddingProvider {
			/**
			 * Create the deterministic embedding fixture.
			 *
			 * @param string $id Provider ID exposed by the fixture.
			 */
			public function __construct( private readonly string $id ) {
			}

			/** Return the configured provider ID. */
			public function provider_id(): string {
				return $this->id;
			}

			/** Test fixtures are available without network work. */
			public function available(): bool {
				return true;
			}

			/**
			 * Embedding is outside this composition contract.
			 *
			 * @param EmbeddingRequest $request Embedding request that must never execute here.
			 * @throws LogicException Always, because embedding is outside composition scope.
			 */
			public function embed( EmbeddingRequest $request ): EmbeddingResult {
				throw new LogicException( 'Embedding must not run while composing semantic retrieval.' );
			}
		};
	}

	/**
	 * Build a deterministic raw-vector search registry fixture.
	 *
	 * @param string $id Stable fixture store ID.
	 */
	private function search_store( string $id ): VectorSearchStore {
		return new class( $id ) implements VectorSearchStore {
			/**
			 * Create the deterministic store fixture.
			 *
			 * @param string $id Stable fixture store ID.
			 */
			public function __construct( private readonly string $id ) {
			}

			/** Return the stable fixture store ID. */
			public function store_id(): string {
				return $this->id;
			}

			/** Declare only raw-vector search capability. */
			public function capabilities(): VectorStoreCapabilities {
				return new VectorStoreCapabilities( false, false, true );
			}

			/** Test fixtures are healthy without external work. */
			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}

			/**
			 * Search is outside this composition contract.
			 *
			 * @param VectorSearchRequest $request Search request that must never execute here.
			 * @throws LogicException Always, because search is outside composition scope.
			 */
			public function search( VectorSearchRequest $request ): VectorSearchResult {
				throw new LogicException( 'Vector search must not run while composing semantic retrieval.' );
			}
		};
	}
}
