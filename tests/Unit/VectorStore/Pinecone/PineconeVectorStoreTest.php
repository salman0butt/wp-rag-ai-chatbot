<?php
/**
 * Pinecone vector-store adapter tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\Pinecone;

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
 * Verifies offline Pinecone adapter behavior and network boundaries.
 */
final class PineconeVectorStoreTest extends TestCase {
	/** Pinecone data-plane endpoints must be administrator-owned HTTPS origins. */
	public function test_config_rejects_unsafe_endpoints(): void {
		$class = 'WpRagAiChatbot\\VectorStore\\Pinecone\\PineconeConfig';
		self::assertTrue( class_exists( $class ), 'PineconeConfig must exist.' );
		foreach ( array( 'http://index.example.test', 'https://user:index.example.test', 'https://index.example.test/path?token=x', 'https://index.example.test/#fragment' ) as $endpoint ) {
			try {
				new $class( $endpoint, 'secret', 'docs-index' );
				self::fail( 'Unsafe Pinecone endpoint must be rejected.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/** Upsert must stay namespace-scoped and keep credentials at the transport boundary. */
	public function test_upsert_maps_namespace_vector_metadata_and_secret_header(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_index_response(), new HttpResponse( 200, array(), '{"upsertedCount":1}' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$record     = new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint(), array( 'lang' => 'en' ) );

		$result = $store->upsert( $record );
		self::assertTrue( $result->changed );
		self::assertCount( 2, $transport->requests );
		self::assertStringEndsWith( '/indexes/docs-index', $transport->requests[0]->url );
		$request = $transport->requests[1];
		self::assertSame( 'POST', $request->method );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'secret', $request->headers['Api-Key'] ?? null );
		self::assertStringEndsWith( '/vectors/upsert', $request->url );
		self::assertSame( $this->physical_namespace(), $request->json_body['namespace'] ?? null );
		self::assertSame( 'chunk:1', $request->json_body['vectors'][0]['id'] ?? null );
		self::assertSame( array( 1.0, 0.0 ), $request->json_body['vectors'][0]['values'] ?? null );
		self::assertSame( $collection->profile->fingerprint(), $request->json_body['vectors'][0]['metadata']['_wp_rag_fingerprint'] ?? null );
		self::assertSame( 'en', $request->json_body['vectors'][0]['metadata']['lang'] ?? null );
	}

	/** Delete must be stable-ID and namespace scoped. */
	public function test_delete_is_namespace_scoped_and_uses_stable_id(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_index_response(), new HttpResponse( 200, array(), '{}' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$result     = $store->delete( $collection, 'chunk:1' );

		self::assertTrue( $result->changed );
		self::assertCount( 2, $transport->requests );
		$request = $transport->requests[1];
		self::assertStringEndsWith( '/vectors/delete', $request->url );
		self::assertSame( array( 'chunk:1' ), $request->json_body['ids'] ?? null );
		self::assertSame( $this->physical_namespace(), $request->json_body['namespace'] ?? null );
	}

	/** Search must map the portable contract onto Pinecone query semantics. */
	public function test_search_maps_filter_top_k_namespace_and_results(): void {
		$collection = $this->collection();
		$body       = '{"matches":[{"id":"chunk:2","score":0.9,"metadata":{"_wp_rag_fingerprint":"' . $collection->profile->fingerprint() . '","lang":"en"}}]}' ;
		$transport  = new QdrantFakeTransport( array( $this->compatible_index_response(), new HttpResponse( 200, array(), $body ) ) );
		$store      = $this->store( $transport );
		$request    = new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $collection->profile->fingerprint(), new EqualsFilter( 'lang', 'en' ) );
		$result     = $store->search( $request );

		self::assertCount( 1, $result->matches );
		self::assertSame( 'chunk:2', $result->matches[0]->id );
		self::assertSame( 0.9, $result->matches[0]->score );
		self::assertSame( 'en', $result->matches[0]->metadata['lang'] ?? null );
		$query_request = $transport->requests[1];
		self::assertStringEndsWith( '/query', $query_request->url );
		self::assertSame( $this->physical_namespace(), $query_request->json_body['namespace'] ?? null );
		self::assertSame( array( 1.0, 0.0 ), $query_request->json_body['vector'] ?? null );
		self::assertSame( 5, $query_request->json_body['topK'] ?? null );
		self::assertTrue( $query_request->json_body['includeMetadata'] ?? false );
		self::assertSame( $collection->profile->fingerprint(), $query_request->json_body['filter']['$and'][0]['_wp_rag_fingerprint']['$eq'] ?? null );
		self::assertSame( 'en', $query_request->json_body['filter']['$and'][1]['lang']['$eq'] ?? null );
	}

	/** Search results are untrusted and must preserve compatibility isolation. */
	public function test_search_rejects_mismatched_response_fingerprint(): void {
		$body       = '{"matches":[{"id":"chunk:2","score":0.9,"metadata":{"_wp_rag_fingerprint":"wrong-profile","lang":"en"}}]}' ;
		$transport  = new QdrantFakeTransport( array( $this->compatible_index_response(), new HttpResponse( 200, array(), $body ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$request    = new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $collection->profile->fingerprint() );

		$this->expectException( VectorStoreException::class );
		$this->expectExceptionMessage( 'Pinecone query response is invalid.' );
		$store->search( $request );
	}

	/** Search results must not exceed the caller's bounded top-K contract. */
	public function test_search_rejects_response_exceeding_top_k(): void {
		$collection  = $this->collection();
		$fingerprint = $collection->profile->fingerprint();
		$body        = '{"matches":['
			. '{"id":"chunk:1","score":0.9,"metadata":{"_wp_rag_fingerprint":"' . $fingerprint . '"}},'
			. '{"id":"chunk:2","score":0.8,"metadata":{"_wp_rag_fingerprint":"' . $fingerprint . '"}}]}' ;
		$transport   = new QdrantFakeTransport( array( $this->compatible_index_response(), new HttpResponse( 200, array(), $body ) ) );
		$store       = $this->store( $transport );
		$request     = new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 1, $fingerprint );

		$this->expectException( VectorStoreException::class );
		$this->expectExceptionMessage( 'Pinecone query response is invalid.' );
		$store->search( $request );
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

	/** Remote index dimensions and metric must match before data-plane writes execute. */
	public function test_adapter_rejects_incompatible_remote_index_before_write(): void {
		$bad        = new HttpResponse( 200, array(), '{"name":"docs-index","dimension":3,"metric":"cosine","host":"docs-example.svc.us-east-1.pinecone.io"}' );
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

	/** External response bodies and credentials must not leak through normalized errors. */
	public function test_external_failure_is_sanitized(): void {
		$transport  = new QdrantFakeTransport( array( $this->compatible_index_response(), new HttpResponse( 500, array(), 'opaque-secret-provider-body' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		try {
			$store->upsert( new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint() ) );
			self::fail( 'Pinecone failure must be normalized.' );
		} catch ( VectorStoreException $exception ) {
			self::assertSame( VectorStoreErrorCode::OPERATION_FAILED, $exception->error_code );
			self::assertStringNotContainsString( 'opaque-secret-provider-body', $exception->getMessage() );
			self::assertStringNotContainsString( 'secret', $exception->getMessage() );
		}
	}

	/** Explicit health checks may perform one bounded control-plane request. */
	public function test_health_uses_bounded_index_description(): void {
		$transport = new QdrantFakeTransport( array( $this->compatible_index_response() ) );
		$health    = $this->store( $transport )->health();

		self::assertTrue( $health->healthy );
		self::assertCount( 1, $transport->requests );
		self::assertSame( 'GET', $transport->requests[0]->method );
		self::assertStringEndsWith( '/indexes/docs-index', $transport->requests[0]->url );
		self::assertSame( 0, $transport->requests[0]->redirection );
	}

	/**
	 * Create the adapter under test using only offline dependencies.
	 *
	 * @param QdrantFakeTransport $transport Fake single-send transport.
	 */
	private function store( QdrantFakeTransport $transport ): object {
		$config_class = 'WpRagAiChatbot\\VectorStore\\Pinecone\\PineconeConfig';
		$store_class  = 'WpRagAiChatbot\\VectorStore\\Pinecone\\PineconeVectorStore';
		self::assertTrue( class_exists( $config_class ), 'PineconeConfig must exist.' );
		self::assertTrue( class_exists( $store_class ), 'PineconeVectorStore must exist.' );
		return new $store_class( new $config_class( 'https://docs-example.svc.us-east-1.pinecone.io', 'secret', 'docs-index' ), $this->collection()->profile, $transport );
	}

	/** Return the compatible collection fixture. */
	private function collection(): VectorCollection {
		return new VectorCollection( 'docs', new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ), DistanceMetric::COSINE ) );
	}

	/** Return the compatibility-isolated Pinecone namespace. */
	private function physical_namespace(): string {
		return 'docs-' . substr( $this->collection()->profile->fingerprint(), 0, 16 );
	}

	/** Return Pinecone index metadata matching the configured index profile. */
	private function compatible_index_response(): HttpResponse {
		return new HttpResponse( 200, array(), '{"name":"docs-index","dimension":2,"metric":"cosine","host":"docs-example.svc.us-east-1.pinecone.io"}' );
	}
}
