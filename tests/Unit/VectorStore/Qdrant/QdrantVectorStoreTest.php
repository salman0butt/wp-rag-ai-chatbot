<?php
/**
 * Qdrant vector-store adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\Qdrant;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Tests\Support\VectorStore\QdrantFakeTransport;
use WpRagAiChatbot\VectorStore\Filter\EqualsFilter;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorStoreErrorCode;
use WpRagAiChatbot\VectorStore\VectorStoreException;

/**
 * Verifies offline Qdrant adapter behavior and network boundaries.
 */
final class QdrantVectorStoreTest extends TestCase {
	/**
	 * Qdrant endpoints must be administrator-owned HTTPS origins.
	 */
	public function test_config_rejects_unsafe_endpoints(): void {
		$class = 'WpRagAiChatbot\\VectorStore\\Qdrant\\QdrantConfig';
		self::assertTrue( class_exists( $class ), 'QdrantConfig must exist.' );
		foreach ( array( 'http://qdrant.example.test', 'https://user:qdrant.example.test', 'https://qdrant.example.test/path?token=x', 'https://qdrant.example.test/#fragment' ) as $endpoint ) {
			try {
				new $class( $endpoint, 'secret' );
				self::fail( 'Unsafe Qdrant endpoint must be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/**
	 * Upsert must use a deterministic Qdrant UUID while preserving plugin identity.
	 */
	public function test_upsert_maps_stable_id_vector_metadata_and_secret_header(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), '{"status":"ok"}' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$record     = new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint(), array( 'lang' => 'en' ) );

		$result = $store->upsert( $record );
		self::assertTrue( $result->changed );
		self::assertCount( 2, $transport->requests );
		$request = $transport->requests[1];
		self::assertSame( 'PUT', $request->method );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'secret', $request->headers['api-key'] ?? null );
		self::assertStringContainsString( '/collections/docs/points', $request->url );
		$id = $request->json_body['points'][0]['id'] ?? null;
		self::assertIsString( $id );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id );
		self::assertSame( array( 1.0, 0.0 ), $request->json_body['points'][0]['vector'] ?? null );
		self::assertSame( 'chunk:1', $request->json_body['points'][0]['payload']['_wp_rag_id'] ?? null );
		self::assertSame( $collection->profile->fingerprint(), $request->json_body['points'][0]['payload']['_wp_rag_fingerprint'] ?? null );
		self::assertSame( 'en', $request->json_body['points'][0]['payload']['lang'] ?? null );
	}

	/**
	 * Delete must derive the same Qdrant UUID as upsert for the stable plugin ID.
	 */
	public function test_delete_is_collection_scoped_and_uses_stable_uuid_mapping(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), '{"status":"ok"}' ), $this->compatible_collection_response(), new HttpResponse( 200, array(), '{"status":"ok"}' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$record     = new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint() );
		$store->upsert( $record );
		$result = $store->delete( $collection, 'chunk:1' );

		self::assertTrue( $result->changed );
		$upsert_id = $transport->requests[1]->json_body['points'][0]['id'] ?? null;
		$delete_id = $transport->requests[3]->json_body['points'][0] ?? null;
		self::assertSame( $upsert_id, $delete_id );
		self::assertStringContainsString( '/collections/docs/points/delete', $transport->requests[3]->url );
	}

	/**
	 * Search must use the current query API and map portable filters/results.
	 */
	public function test_search_maps_portable_filter_top_k_and_results(): void {
		$body       = '{"result":{"points":[{"id":"936da01f-9abd-5d9d-80c7-02af85c822a8","score":0.9,"payload":{"_wp_rag_id":"chunk:2","_wp_rag_fingerprint":"ignored-by-test","lang":"en"}}]}}';
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 200, array(), $body ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$request    = new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $collection->profile->fingerprint(), new EqualsFilter( 'lang', 'en' ) );
		$result     = $store->search( $request );

		self::assertCount( 1, $result->matches );
		self::assertSame( 'chunk:2', $result->matches[0]->id );
		self::assertSame( 0.9, $result->matches[0]->score );
		self::assertSame( 'en', $result->matches[0]->metadata['lang'] ?? null );
		$query_request = $transport->requests[1];
		self::assertStringContainsString( '/collections/docs/points/query', $query_request->url );
		self::assertSame( array( 1.0, 0.0 ), $query_request->json_body['query'] ?? null );
		self::assertSame( 5, $query_request->json_body['limit'] ?? null );
		self::assertTrue( $query_request->json_body['with_payload'] ?? false );
		self::assertSame( 'lang', $query_request->json_body['filter']['must'][0]['key'] ?? null );
		self::assertSame( 'en', $query_request->json_body['filter']['must'][0]['match']['value'] ?? null );
	}

	/**
	 * Collection profile mismatch must fail before any network call.
	 */
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

	/**
	 * Remote collection dimensions/metric must match before writes execute.
	 */
	public function test_adapter_rejects_incompatible_remote_collection_before_write(): void {
		$bad        = new HttpResponse( 200, array(), '{"result":{"config":{"params":{"vectors":{"size":3,"distance":"Cosine"}}}}}' );
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

	/**
	 * External response bodies and credentials must not leak through normalized errors.
	 */
	public function test_external_failure_is_sanitized(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_collection_response(), new HttpResponse( 500, array(), 'opaque-secret-provider-body' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		try {
			$store->upsert( new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint() ) );
			self::fail( 'Qdrant failure must be normalized.' );
		} catch ( VectorStoreException $exception ) {
			self::assertSame( VectorStoreErrorCode::OPERATION_FAILED, $exception->error_code );
			self::assertStringNotContainsString( 'opaque-secret-provider-body', $exception->getMessage() );
			self::assertStringNotContainsString( 'secret', $exception->getMessage() );
		}
	}

	/**
	 * Explicit health checks may perform one bounded remote request.
	 */
	public function test_health_uses_bounded_health_endpoint(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '"healthz check passed"' ) ) );
		$health    = $this->store( $transport )->health();

		self::assertTrue( $health->healthy );
		self::assertCount( 1, $transport->requests );
		self::assertSame( 'GET', $transport->requests[0]->method );
		self::assertStringEndsWith( '/healthz', $transport->requests[0]->url );
		self::assertSame( 0, $transport->requests[0]->redirection );
	}

	/**
	 * Create the adapter under test using only offline dependencies.
	 *
	 * @param QdrantFakeTransport $transport Fake single-send transport.
	 */
	private function store( QdrantFakeTransport $transport ): object {
		$config_class = 'WpRagAiChatbot\\VectorStore\\Qdrant\\QdrantConfig';
		$store_class  = 'WpRagAiChatbot\\VectorStore\\Qdrant\\QdrantVectorStore';
		self::assertTrue( class_exists( $config_class ), 'QdrantConfig must exist.' );
		self::assertTrue( class_exists( $store_class ), 'QdrantVectorStore must exist.' );
		return new $store_class( new $config_class( 'https://qdrant.example.test', 'secret' ), $this->collection()->profile, $transport );
	}

	/**
	 * Return the compatible collection fixture.
	 */
	private function collection(): VectorCollection {
		return new VectorCollection( 'docs', new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ), DistanceMetric::COSINE ) );
	}

	/**
	 * Return Qdrant collection metadata matching the configured index profile.
	 */
	private function compatible_collection_response(): HttpResponse {
		return new HttpResponse( 200, array(), '{"result":{"config":{"params":{"vectors":{"size":2,"distance":"Cosine"}}}}}' );
	}
}
