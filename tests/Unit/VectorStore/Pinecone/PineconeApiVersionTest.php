<?php
/**
 * Pinecone API-version contract test.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\VectorStore\Pinecone;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Embeddings\VectorIndexProfile;
use WpRagAiChatbot\Providers\Http\HttpResponse;
use WpRagAiChatbot\Tests\Support\VectorStore\QdrantFakeTransport;
use WpRagAiChatbot\VectorStore\Pinecone\PineconeConfig;
use WpRagAiChatbot\VectorStore\Pinecone\PineconeVectorStore;

/**
 * Verifies direct Pinecone REST calls pin a supported stable API version.
 */
final class PineconeApiVersionTest extends TestCase {
	/** Every adapter-owned request must pin the current supported API contract. */
	public function test_health_request_pins_supported_api_version(): void {
		$profile   = new VectorIndexProfile( new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ), DistanceMetric::COSINE );
		$transport = new QdrantFakeTransport(
			array(
				new HttpResponse( 200, array(), '{"name":"docs-index","dimension":2,"metric":"cosine","host":"docs-example.svc.us-east-1.pinecone.io"}' ),
			)
		);
		$store     = new PineconeVectorStore(
			new PineconeConfig( 'https://docs-example.svc.us-east-1.pinecone.io', 'secret', 'docs-index' ),
			$profile,
			$transport
		);

		self::assertTrue( $store->health()->healthy );
		self::assertCount( 1, $transport->requests );
		self::assertSame( '2025-10', $transport->requests[0]->headers['X-Pinecone-Api-Version'] ?? null );
	}
}
