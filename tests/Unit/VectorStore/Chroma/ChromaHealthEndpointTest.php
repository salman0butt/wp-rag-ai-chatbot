<?php
/**
 * Chroma health endpoint regression test.
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

/**
 * Keeps the adapter aligned with the current public Chroma v2 health API.
 */
final class ChromaHealthEndpointTest extends TestCase {
	/** Health must use Chroma's current v2 heartbeat endpoint exactly once. */
	public function test_health_uses_current_v2_heartbeat_endpoint(): void {
		$transport = new QdrantFakeTransport( array( new HttpResponse( 200, array(), '{"nanosecond heartbeat":1}' ) ) );
		$profile   = new VectorIndexProfile(
			new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ),
			DistanceMetric::COSINE
		);
		$store     = new ChromaVectorStore(
			new ChromaConfig( 'https://chroma.example.test', 'tenant', 'database', 'secret' ),
			$profile,
			$transport
		);

		self::assertTrue( $store->health()->healthy );
		self::assertCount( 1, $transport->requests );
		self::assertSame( 'GET', $transport->requests[0]->method );
		self::assertStringEndsWith( '/api/v2/heartbeat', $transport->requests[0]->url );
		self::assertSame( 0, $transport->requests[0]->redirection );
	}
}
