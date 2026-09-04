<?php
/**
 * M07 indexing-plan to M08 embedding/vector integration tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Integration\Embeddings;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingBatchConfig;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\EmbeddingService;
use WpRagAiChatbot\Embeddings\IndexEmbeddingExecutor;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Indexing\Chunking\ChunkRecord;
use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Providers\EmbeddingResult;
use WpRagAiChatbot\Providers\EmbeddingUsage;
use WpRagAiChatbot\Providers\EmbeddingVector;
use WpRagAiChatbot\Tests\Support\Embeddings\RecordingEmbeddingProvider;
use WpRagAiChatbot\Tests\Support\VectorStore\InMemoryVectorStore;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;

// phpcs:disable WordPress.NamingConventions -- M07 domain DTO properties intentionally use the approved camelCase contract.
// phpcs:disable Generic.Formatting.MultipleStatementAlignment -- Test fixtures favor local grouping over alignment churn.
/**
 * Verifies bounded deterministic execution of accepted M07 index plans.
 */
final class IndexEmbeddingExecutorTest extends TestCase {
	/**
	 * Only planned upserts are embedded; deletes execute without re-embedding refresh/unchanged chunks.
	 */
	public function test_execute_embeds_only_upserts_and_applies_planned_deletes(): void {
		$profile = $this->profile();
		$collection = new VectorCollection( 'knowledge', $profile );
		$upsert_a = $this->chunk( 'upsert-a', 'First content', 0, $profile->fingerprint() );
		$upsert_b = $this->chunk( 'upsert-b', 'Second content', 1, $profile->fingerprint() );
		$refresh = $this->chunk( 'refresh', 'Refresh content', 2, $profile->fingerprint() );
		$unchanged = $this->chunk( 'unchanged', 'Unchanged content', 3, $profile->fingerprint() );
		$delete_key = hash( 'sha256', 'delete' );
		$provider = new RecordingEmbeddingProvider(
			array(
				new EmbeddingResult(
					'test-embedding',
					'embed-model',
					array(
						new EmbeddingVector( 0, array( 1.0, 0.0 ) ),
						new EmbeddingVector( 1, array( 0.0, 1.0 ) ),
					),
					EmbeddingUsage::input_tokens( 4 )
				),
			)
		);
		$store = new InMemoryVectorStore( 'memory' );
		$service = new EmbeddingService( $provider, new EmbeddingBatchConfig( 10 ) );
		$executor = new IndexEmbeddingExecutor( $service, $store, $store, $collection );
		$plan = new IndexPlan(
			array( $upsert_a, $upsert_b ),
			array( $refresh ),
			array( $delete_key ),
			array( $unchanged ),
			array()
		);

		$executor->execute( $plan );

		self::assertCount( 1, $provider->requests );
		self::assertSame( array( 'First content', 'Second content' ), $provider->requests[0]->inputs );
		self::assertSame( 'embed-model', $provider->requests[0]->model );
		self::assertSame( 2, $provider->requests[0]->dimensions );

		$result = $store->search(
			new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 10, $profile->fingerprint() )
		);
		self::assertSame(
			array( $upsert_a->chunkKey, $upsert_b->chunkKey ),
			array_map( static fn ( $vector_match ): string => $vector_match->id, $result->matches )
		);
		self::assertSame( $upsert_a->documentKey, $result->matches[0]->metadata['document_key'] );
		self::assertSame( $upsert_a->contentHash, $result->matches[0]->metadata['content_hash'] );
		self::assertArrayNotHasKey( 'private_payload', $result->matches[0]->metadata );
	}

	/**
	 * A chunk tied to another compatibility profile fails before any provider or store operation.
	 */
	public function test_execute_rejects_incompatible_chunk_before_embedding(): void {
		$profile = $this->profile();
		$collection = new VectorCollection( 'knowledge', $profile );
		$provider = new RecordingEmbeddingProvider( array() );
		$store = new InMemoryVectorStore( 'memory' );
		$executor = new IndexEmbeddingExecutor(
			new EmbeddingService( $provider, new EmbeddingBatchConfig( 10 ) ),
			$store,
			$store,
			$collection
		);
		$plan = new IndexPlan(
			array( $this->chunk( 'incompatible', 'Content', 0, str_repeat( 'f', 64 ) ) ),
			array(),
			array(),
			array(),
			array()
		);

		$this->expectException( InvalidArgumentException::class );
		$executor->execute( $plan );
	}

	/**
	 * A collection profile for another embedding provider must fail before a paid provider request.
	 */
	public function test_execute_rejects_provider_profile_mismatch_before_embedding(): void {
		$profile = $this->profile( 'other-provider' );
		$collection = new VectorCollection( 'knowledge', $profile );
		$provider = new RecordingEmbeddingProvider(
			array(
				new EmbeddingResult(
					'test-embedding',
					'embed-model',
					array( new EmbeddingVector( 0, array( 1.0, 0.0 ) ) ),
					EmbeddingUsage::input_tokens( 1 )
				),
			)
		);
		$store = new InMemoryVectorStore( 'memory' );
		$executor = new IndexEmbeddingExecutor(
			new EmbeddingService( $provider, new EmbeddingBatchConfig( 10 ) ),
			$store,
			$store,
			$collection
		);
		$plan = new IndexPlan(
			array( $this->chunk( 'provider-mismatch', 'Content', 0, $profile->fingerprint() ) ),
			array(),
			array(),
			array(),
			array()
		);

		try {
			$executor->execute( $plan );
			self::fail( 'Expected embedding provider/profile mismatch to fail closed.' );
		} catch ( InvalidArgumentException ) {
			self::assertCount( 0, $provider->requests );
		}
	}

	/**
	 * Oversized upsert plans are rejected before constructing an unbounded embedding request.
	 */
	public function test_execute_rejects_more_than_the_upsert_execution_bound_before_embedding(): void {
		$profile = $this->profile();
		$collection = new VectorCollection( 'knowledge', $profile );
		$provider = new RecordingEmbeddingProvider( array() );
		$store = new InMemoryVectorStore( 'memory' );
		$executor = new IndexEmbeddingExecutor(
			new EmbeddingService( $provider, new EmbeddingBatchConfig( 10 ) ),
			$store,
			$store,
			$collection
		);
		$upserts = array();
		for ( $index = 0; $index < 1001; ++$index ) {
			$upserts[] = $this->chunk( 'chunk-' . $index, 'Content ' . $index, $index, $profile->fingerprint() );
		}

		$this->expectException( InvalidArgumentException::class );
		$executor->execute( new IndexPlan( $upserts, array(), array(), array(), array() ) );
	}

	/**
	 * Oversized delete plans are rejected before starting synchronous store mutations.
	 */
	public function test_execute_rejects_more_than_the_delete_execution_bound(): void {
		$profile = $this->profile();
		$collection = new VectorCollection( 'knowledge', $profile );
		$provider = new RecordingEmbeddingProvider( array() );
		$store = new InMemoryVectorStore( 'memory' );
		$executor = new IndexEmbeddingExecutor(
			new EmbeddingService( $provider, new EmbeddingBatchConfig( 10 ) ),
			$store,
			$store,
			$collection
		);
		$delete_keys = array();
		for ( $index = 0; $index < 1001; ++$index ) {
			$delete_keys[] = hash( 'sha256', 'delete-' . $index );
		}

		$this->expectException( InvalidArgumentException::class );
		$executor->execute( new IndexPlan( array(), array(), $delete_keys, array(), array() ) );
	}

	/**
	 * Build the selected test compatibility profile.
	 *
	 * @param string $provider_id Embedding provider ID.
	 */
	private function profile( string $provider_id = 'test-embedding' ): VectorIndexProfile {
		return new VectorIndexProfile(
			new EmbeddingProfile( $provider_id, 'embed-model', 2, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);
	}

	/**
	 * Build one valid deterministic M07 chunk.
	 *
	 * @param string $seed Stable test seed.
	 * @param string $content Chunk text.
	 * @param int    $sequence Chunk sequence.
	 * @param string $compatibility_key Embedding compatibility fingerprint.
	 */
	private function chunk( string $seed, string $content, int $sequence, string $compatibility_key ): ChunkRecord {
		return new ChunkRecord(
			hash( 'sha256', $seed ),
			hash( 'sha256', 'document' ),
			7,
			'post',
			'Title',
			'https://example.test/post',
			$content,
			hash( 'sha256', $content ),
			'v1',
			hash( 'sha256', 'document-content' ),
			'en',
			'public',
			$sequence,
			null,
			array( 'Heading' ),
			2,
			'v1',
			hash( 'sha256', 'chunking' ),
			$compatibility_key,
			array( 'private_payload' => array( 'must-not-cross-boundary' ) )
		);
	}
}
// phpcs:enable Generic.Formatting.MultipleStatementAlignment
// phpcs:enable WordPress.NamingConventions
