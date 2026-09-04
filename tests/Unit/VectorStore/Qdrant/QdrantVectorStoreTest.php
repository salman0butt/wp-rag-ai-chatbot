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
use WpRagAiChatbot\VectorStore\VectorStoreException;

/**
 * Verifies offline Qdrant adapter behavior and network boundaries.
 */
final class QdrantVectorStoreTest extends TestCase {
	/**
	 * Qdrant endpoints must be HTTPS.
	 */
	public function test_config_rejects_non_https_endpoint(): void {
		$class = 'WpRagAiChatbot\\VectorStore\\Qdrant\\QdrantConfig';
		self::assertTrue( class_exists( $class ), 'QdrantConfig must exist.' );
		$this->expectException( InvalidArgumentException::class );
		new $class( 'http://qdrant.example.test', 'secret' );
	}

	/**
	 * Upsert must preserve stable IDs, vectors, metadata, and single-send auth.
	 */
	public function test_upsert_maps_stable_id_vector_metadata_and_secret_header_once(): void {
		$transport  = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '{"status":"ok"}' ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$record     = new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $collection->profile->fingerprint(), array( 'lang' => 'en' ) );

		$result = $store->upsert( $record );
		self::assertTrue( $result->changed );
		self::assertCount( 1, $transport->requests );
		$request = $transport->requests[0];
		self::assertSame( 'PUT', $request->method );
		self::assertSame( 0, $request->redirection );
		self::assertSame( 'secret', $request->headers['api-key'] ?? null );
		self::assertStringContainsString( '/collections/docs/points', $request->url );
		self::assertSame( 'chunk:1', $request->json_body['points'][0]['id'] ?? null );
		self::assertSame( array( 1.0, 0.0 ), $request->json_body['points'][0]['vector'] ?? null );
		self::assertSame( 'en', $request->json_body['points'][0]['payload']['lang'] ?? null );
	}

	/**
	 * Delete must remain collection-scoped and single-send.
	 */
	public function test_delete_is_collection_scoped_and_single_send(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '{"status":"ok"}' ) ) );
		$store     = $this->store( $transport );
		$result    = $store->delete( $this->collection(), 'chunk:1' );

		self::assertTrue( $result->changed );
		self::assertCount( 1, $transport->requests );
		self::assertSame( array( 'chunk:1' ), $transport->requests[0]->json_body['points'] ?? null );
	}

	/**
	 * Search must map portable filters and bounded top-K into Qdrant.
	 */
	public function test_search_maps_portable_filter_top_k_and_results(): void {
		$body       = '{"result":[{"id":"chunk:2","score":0.9,"payload":{"lang":"en"}}]}';
		$transport  = new QdrantFakeTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$store      = $this->store( $transport );
		$collection = $this->collection();
		$request    = new VectorSearchRequest( $collection, array( 1.0, 0.0 ), 5, $collection->profile->fingerprint(), new EqualsFilter( 'lang', 'en' ) );
		$result     = $store->search( $request );

		self::assertCount( 1, $result->matches );
		self::assertSame( 'chunk:2', $result->matches[0]->id );
		self::assertSame( 0.9, $result->matches[0]->score );
		self::assertSame( 5, $transport->requests[0]->json_body['limit'] ?? null );
		self::assertSame( 'lang', $transport->requests[0]->json_body['filter']['must'][0]['key'] ?? null );
	}

	/**
	 * Compatibility mismatch must fail before any network write.
	 */
	public function test_adapter_does_not_send_when_collection_profile_differs_from_config(): void {
		$transport = new QdrantFakeTransport( array() );
		$store     = $this->store( $transport );
		$other     = new VectorCollection( 'docs', new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ), DistanceMetric::DOT ) );
		$this->expectException( VectorStoreException::class );
		try {
			$store->upsert( new VectorRecord( $other, 'chunk:1', array( 1.0, 0.0 ), $other->profile->fingerprint() ) );
		} finally {
			self::assertCount( 0, $transport->requests );
		}
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
}
