<?php
/**
 * Chroma vector-store adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\Chroma;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Tests\Support\VectorStore\QdrantFakeTransport;
use WpRagAiChatbot\VectorStore\Filter\AndFilter;
use WpRagAiChatbot\VectorStore\Filter\EqualsFilter;
use WpRagAiChatbot\VectorStore\Filter\InFilter;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorStoreErrorCode;
use WpRagAiChatbot\VectorStore\VectorStoreException;

/**
 * Verifies offline Chroma v2 behavior and network boundaries.
 */
final class ChromaVectorStoreTest extends TestCase {
	/** Chroma endpoints must be fixed administrator-owned HTTPS origins. */
	public function test_config_rejects_unsafe_endpoints_and_scope(): void {
		$config_class = 'WpRagAiChatbot\\VectorStore\\Chroma\\ChromaConfig';
		self::assertTrue( class_exists( $config_class ), 'ChromaConfig must exist.' );
		foreach ( array( 'http://chroma.example.test', 'https://user@chroma.example.test', 'https://chroma.example.test/path', 'https://chroma.example.test?token=x', 'https://chroma.example.test/#fragment' ) as $endpoint ) {
			try {
				new $config_class( $endpoint, 'tenant', 'database', 'secret' );
				self::fail( 'Unsafe Chroma endpoint must be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}

		foreach ( array( '', '../tenant', 'tenant/other' ) as $tenant ) {
			$this->expect_invalid_config( $config_class, 'https://chroma.example.test', $tenant, 'database' );
		}
		foreach ( array( '', '../database', 'database/other' ) as $database ) {
			$this->expect_invalid_config( $config_class, 'https://chroma.example.test', 'tenant', $database );
		}
	}

	/** Upsert must resolve one profile-isolated collection and send explicit embeddings. */
	public function test_upsert_maps_collection_embedding_metadata_and_secret_header(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), '{}' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$record     = new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint(), array( 'lang' => 'en' ) );

		$result = $store->upsert( $record );
		self::assertTrue( $result->changed );
		self::assertCount( 2, $transport->requests );
		$inspect = $transport->requests[0];
		self::assertSame( 'GET', $inspect->method );
		self::assertStringEndsWith( '/api/v2/tenants/tenant/databases/database/collections/' . $this->physical_collection_name(), $inspect->url );
		$request = $transport->requests[1];
		self::assertSame( 'POST', $request->method );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'secret', $request->headers['x-chroma-token'] ?? null );
		self::assertStringEndsWith( '/collections/11111111-1111-4111-8111-111111111111/upsert', $request->url );
		self::assertSame( array( 'chunk:1' ), $request->json_body['ids'] ?? null );
		self::assertSame( array( array( 1.0, 0.0 ) ), $request->json_body['embeddings'] ?? null );
		self::assertSame( $collection->profile->fingerprint(), $request->json_body['metadatas'][0]['_wp_rag_fingerprint'] ?? null );
		self::assertSame( 'en', $request->json_body['metadatas'][0]['lang'] ?? null );
	}

	/** Delete must resolve the collection and remain stable-ID scoped. */
	public function test_delete_is_collection_scoped_and_uses_stable_id(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), '{"deleted":1}' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$result     = $store->delete( $collection, 'chunk:1' );

		self::assertTrue( $result->changed );
		self::assertCount( 2, $transport->requests );
		$request = $transport->requests[1];
		self::assertStringEndsWith( '/collections/11111111-1111-4111-8111-111111111111/delete', $request->url );
		self::assertSame( array( 'chunk:1' ), $request->json_body['ids'] ?? null );
	}

	/** Search must use explicit query embeddings, portable filters, and bounded result fields. */
	public function test_search_maps_filters_top_k_and_distance_to_score(): void {
		$collection  = $this->collection();
		$fingerprint = $collection->profile->fingerprint();
		$body        = '{"ids":[["chunk:2","chunk:1"]],"include":["distances","metadatas"],"distances":[[0.1,0.2]],"metadatas":[[{"_wp_rag_fingerprint":"' . $fingerprint . '","lang":"en"},{"_wp_rag_fingerprint":"' . $fingerprint . '","lang":"en"}]],"documents":null,"embeddings":null,"uris":null}';
		$transport   = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), $body ) ) );
		$store       = $this->store( $transport );
		$filter      = new AndFilter( array( new EqualsFilter( 'lang', 'en' ), new InFilter( 'published', array( true, false ) ) ) );
		$request     = new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $fingerprint, $filter );
		$result      = $store->search( $request );

		self::assertCount( 2, $result->matches );
		self::assertSame( 'chunk:2', $result->matches[0]->id );
		self::assertEqualsWithDelta( 0.9, $result->matches[0]->score, 0.000001 );
		self::assertSame( 'en', $result->matches[0]->metadata['lang'] ?? null );
		$query_request = $transport->requests[1];
		self::assertStringEndsWith( '/collections/11111111-1111-4111-8111-111111111111/query', $query_request->url );
		self::assertSame( array( array( 1.0, 0.0 ) ), $query_request->json_body['query_embeddings'] ?? null );
		self::assertSame( 5, $query_request->json_body['n_results'] ?? null );
		self::assertSame( array( 'distances', 'metadatas' ), $query_request->json_body['include'] ?? null );
		self::assertSame( $fingerprint, $query_request->json_body['where']['$and'][0]['_wp_rag_fingerprint']['$eq'] ?? null );
		self::assertSame( 'en', $query_request->json_body['where']['$and'][1]['lang']['$eq'] ?? null );
		self::assertSame( array( true, false ), $query_request->json_body['where']['$and'][2]['published']['$in'] ?? null );
	}

	/** Collection profile mismatch must fail before any network call. */
	public function test_adapter_does_not_send_when_collection_profile_differs_from_config(): void {
		$transport = new QdrantFakeTransport( array() );
		$store     = $this->store( $transport );
		$other     = new VectorCollection( 'docs', new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ), DistanceMetric::DOT_PRODUCT ) );

		$this->expectException( VectorStoreException::class );
		try {
			$store->upsert( new VectorRecord( $other, 'chunk:1', array( 1.0, 0.0 ), $other->profile->fingerprint() ) );
		} finally {
			self::assertCount( 0, $transport->requests );
		}
	}

	/** Remote Chroma collection dimensions/metric/fingerprint must match before writes. */
	public function test_adapter_rejects_incompatible_remote_collection_before_write(): void {
		$bad        = $this->collection_response( 3, 'cosine', $this->collection()->profile->fingerprint() );
		$transport  = new QdrantFakeTransport( array( $bad ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();

		$this->expectException( VectorStoreException::class );
		try {
			$store->upsert( new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint() ) );
		} finally {
			self::assertCount( 1, $transport->requests );
		}
	}

	/** Search results are untrusted and must preserve compatibility isolation. */
	public function test_search_rejects_mismatched_response_fingerprint(): void {
		$body       = '{"ids":[["chunk:1"]],"include":["distances","metadatas"],"distances":[[0.1]],"metadatas":[[{"_wp_rag_fingerprint":"wrong"}]]}';
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), $body ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$request    = new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $collection->profile->fingerprint() );

		$this->expectException( VectorStoreException::class );
		$this->expectExceptionMessage( 'Chroma query response is invalid.' );
		$store->search( $request );
	}

	/** Search response cardinality must never exceed caller top-K. */
	public function test_search_rejects_response_exceeding_top_k(): void {
		$fingerprint = $this->collection()->profile->fingerprint();
		$body        = '{"ids":[["chunk:1","chunk:2"]],"include":["distances","metadatas"],"distances":[[0.1,0.2]],"metadatas":[[{"_wp_rag_fingerprint":"' . $fingerprint . '"},{"_wp_rag_fingerprint":"' . $fingerprint . '"}]]}';
		$transport   = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), $body ) ) );
		$store       = $this->store( $transport );
		$request     = new VectorSearchRequest( $this->collection(), array( 1.0, 0.0 ), 1, $fingerprint );

		$this->expectException( VectorStoreException::class );
		$this->expectExceptionMessage( 'Chroma query response is invalid.' );
		$store->search( $request );
	}

	/** External response bodies/secrets must be sanitized and a failed write is never retried. */
	public function test_external_failure_is_sanitized_and_not_retried(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 500, array(), 'opaque-secret-provider-body' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		try {
			$store->upsert( new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint() ) );
			self::fail( 'Chroma failure must be normalized.' );
		} catch ( VectorStoreException $exception ) {
			self::assertSame( VectorStoreErrorCode::OPERATION_FAILED, $exception->error_code );
			self::assertStringNotContainsString( 'opaque-secret-provider-body', $exception->getMessage() );
			self::assertStringNotContainsString( 'secret', $exception->getMessage() );
		}
		self::assertCount( 2, $transport->requests );
	}

	/** Explicit health uses one bounded v2 health request and no collection mutation. */
	public function test_health_uses_bounded_v2_healthcheck(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '"ok"' ) ) );
		$health    = $this->store( $transport )->health();

		self::assertTrue( $health->healthy );
		self::assertCount( 1, $transport->requests );
		self::assertSame( 'GET', $transport->requests[0]->method );
		self::assertStringEndsWith( '/api/v2/healthcheck', $transport->requests[0]->url );
		self::assertSame( 0, $transport->requests[0]->redirection );
	}

	/**
	 * Assert one invalid config scope component fails closed.
	 *
	 * @param class-string $config_class Config class.
	 * @param string       $endpoint Chroma origin.
	 * @param string       $tenant Tenant value.
	 * @param string       $database Database value.
	 */
	private function expect_invalid_config( string $config_class, string $endpoint, string $tenant, string $database ): void {
		try {
			new $config_class( $endpoint, $tenant, $database, null );
			self::fail( 'Unsafe Chroma scope must be rejected.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertNotSame( '', $exception->getMessage() );
		}
	}

	/**
	 * Create the adapter under test using only offline dependencies.
	 *
	 * @param QdrantFakeTransport $transport Offline fake HTTP transport.
	 */
	private function store( QdrantFakeTransport $transport ): object {
		$config_class = 'WpRagAiChatbot\\VectorStore\\Chroma\\ChromaConfig';
		$store_class  = 'WpRagAiChatbot\\VectorStore\\Chroma\\ChromaVectorStore';
		self::assertTrue( class_exists( $config_class ), 'ChromaConfig must exist.' );
		self::assertTrue( class_exists( $store_class ), 'ChromaVectorStore must exist.' );
		return new $store_class( new $config_class( 'https://chroma.example.test', 'tenant', 'database', 'secret' ), $this->collection()->profile, $transport );
	}

	/** Return the compatible collection fixture. */
	private function collection(): VectorCollection {
		return new VectorCollection( 'docs', new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ), DistanceMetric::COSINE ) );
	}

	/** Return the compatibility-isolated Chroma physical collection name. */
	private function physical_collection_name(): string {
		return 'wp-' . substr( hash( 'sha256', $this->collection()->id ), 0, 12 ) . '-' . substr( $this->collection()->profile->fingerprint(), 0, 16 );
	}

	/** Return Chroma collection metadata matching the configured profile. */
	private function compatible_collection_response(): HttpResponse {
		return $this->collection_response( 2, 'cosine', $this->collection()->profile->fingerprint() );
	}

	/**
	 * Build one Chroma v2 collection response.
	 *
	 * @param int    $dimension Embedding dimension.
	 * @param string $space Distance metric space.
	 * @param string $fingerprint Compatibility fingerprint.
	 */
	private function collection_response( int $dimension, string $space, string $fingerprint ): HttpResponse {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- PHPUnit unit bootstrap does not load WordPress runtime functions.
		$body = json_encode(
			array(
				'id'                 => '11111111-1111-4111-8111-111111111111',
				'name'               => $this->physical_collection_name(),
				'tenant'             => 'tenant',
				'database'           => 'database',
				'dimension'          => $dimension,
				'metadata'           => array( '_wp_rag_fingerprint' => $fingerprint ),
				'configuration_json' => array( 'hnsw' => array( 'space' => $space ) ),
			),
			JSON_THROW_ON_ERROR
		);

		return new HttpResponse( 200, array(), $body );
	}
}
