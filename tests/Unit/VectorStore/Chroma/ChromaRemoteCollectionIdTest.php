<?php
/**
 * Chroma remote collection ID trust-boundary tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\Chroma;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Tests\Support\VectorStore\QdrantFakeTransport;
use WpRagAiChatbot\VectorStore\Chroma\ChromaConfig;
use WpRagAiChatbot\VectorStore\Chroma\ChromaVectorStore;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorRecord;
use WpRagAiChatbot\VectorStore\VectorStoreException;

/**
 * Ensures untrusted remote collection identifiers fail closed.
 */
final class ChromaRemoteCollectionIdTest extends TestCase {
	/** A non-UUID remote collection ID must fail before a mutation request is sent. */
	public function test_upsert_rejects_malformed_remote_collection_uuid(): void {
		$profile    = new VectorIndexProfile(
			new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);
		$collection = new VectorCollection( 'docs', $profile );
		$physical   = 'wp-' . substr( hash( 'sha256', $collection->id ), 0, 12 ) . '-' . substr( $profile->fingerprint(), 0, 16 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- PHPUnit unit bootstrap does not load WordPress runtime functions.
		$body      = json_encode(
			array(
				'id'                 => '------------------------------------',
				'name'               => $physical,
				'tenant'             => 'tenant',
				'database'           => 'database',
				'dimension'          => 2,
				'metadata'           => array( '_wp_rag_fingerprint' => $profile->fingerprint() ),
				'configuration_json' => array( 'hnsw' => array( 'space' => 'cosine' ) ),
			),
			JSON_THROW_ON_ERROR
		);
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), $body ) ) );
		$store     = new ChromaVectorStore(
			new ChromaConfig( 'https://chroma.example.test', 'tenant', 'database', 'secret' ),
			$profile,
			$transport
		);
		$record    = new VectorRecord( $collection, 'chunk:1', array( 1.0, 0.0 ), $profile->fingerprint() );

		$this->expectException( VectorStoreException::class );
		try {
			$store->upsert( $record );
		} finally {
			self::assertCount( 1, $transport->requests );
		}
	}
}
