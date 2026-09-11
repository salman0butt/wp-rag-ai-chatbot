<?php
/**
 * M13 Playground vector-store resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundVectorStoreResolver;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorSearchResult;
use WpRagAiChatbot\VectorStore\VectorSearchStore;
use WpRagAiChatbot\VectorStore\VectorStore;
use WpRagAiChatbot\VectorStore\VectorStoreCapabilities;
use WpRagAiChatbot\VectorStore\VectorStoreHealth;
use WpRagAiChatbot\VectorStore\VectorStoreRegistry;

/**
 * Proves persisted Playground vector-store selection delegates to the production registry authority.
 */
final class PlaygroundVectorStoreResolverTest extends TestCase {
	/** Persisted store selection must resolve the exact registered search adapter. */
	public function test_resolves_exact_search_store_selected_by_persisted_semantic_configuration(): void {
		$store    = $this->search_store( 'local-wordpress' );
		$registry = new VectorStoreRegistry();
		$registry->register( $store );
		$resolver = new PlaygroundVectorStoreResolver( $registry );

		self::assertSame( $store, $resolver->resolve( $this->semantic( 'local-wordpress' ) ) );
	}

	/** Unknown persisted store IDs must fail closed instead of selecting another store. */
	public function test_rejects_unknown_persisted_store_without_fallback(): void {
		$registry = new VectorStoreRegistry();
		$registry->register( $this->search_store( 'local-wordpress' ) );
		$resolver = new PlaygroundVectorStoreResolver( $registry );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Vector store is not registered.' );
		$resolver->resolve( $this->semantic( 'missing-store' ) );
	}

	/** Persisted stores without raw-vector search support must fail through the registry authority. */
	public function test_rejects_registered_store_without_search_capability(): void {
		$registry = new VectorStoreRegistry();
		$registry->register( $this->base_store( 'write-only' ) );
		$resolver = new PlaygroundVectorStoreResolver( $registry );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Vector store does not support search.' );
		$resolver->resolve( $this->semantic( 'write-only' ) );
	}

	/** Build one deterministic raw-vector search fixture. */
	private function search_store( string $id ): VectorSearchStore {
		return new class( $id ) implements VectorSearchStore {
			public function __construct( private readonly string $id ) {
			}

			public function store_id(): string {
				return $this->id;
			}

			public function capabilities(): VectorStoreCapabilities {
				return new VectorStoreCapabilities( false, false, true );
			}

			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}

			public function search( VectorSearchRequest $request ): VectorSearchResult {
				throw new LogicException( 'Vector search must not run while resolving a store.' );
			}
		};
	}

	/** Build one deterministic store fixture without raw-vector search support. */
	private function base_store( string $id ): VectorStore {
		return new class( $id ) implements VectorStore {
			public function __construct( private readonly string $id ) {
			}

			public function store_id(): string {
				return $this->id;
			}

			public function capabilities(): VectorStoreCapabilities {
				return VectorStoreCapabilities::none();
			}

			public function health(): VectorStoreHealth {
				return VectorStoreHealth::healthy();
			}
		};
	}

	/** Build one persisted semantic configuration with the selected vector-store ID. */
	private function semantic( string $vector_store_id ): PlaygroundSemanticConfiguration {
		return new PlaygroundSemanticConfiguration(
			new EmbeddingProfile( 'openai', 'text-embedding-3-small', 1536, NormalizationMode::L2 ),
			DistanceMetric::COSINE,
			$vector_store_id
		);
	}
}
