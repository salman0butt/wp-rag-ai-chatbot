<?php
/**
 * Pinecone boolean membership filter regression test.
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
use WpRagAiChatbot\Tests\Support\VectorStore\QdrantFakeTransport;
use WpRagAiChatbot\VectorStore\Filter\InFilter;
use WpRagAiChatbot\VectorStore\Pinecone\PineconeConfig;
use WpRagAiChatbot\VectorStore\Pinecone\PineconeVectorStore;
use WpRagAiChatbot\VectorStore\VectorCollection;
use WpRagAiChatbot\VectorStore\VectorSearchRequest;
use WpRagAiChatbot\VectorStore\VectorStoreErrorCode;
use WpRagAiChatbot\VectorStore\VectorStoreException;

/**
 * Pinecone $in only supports string and numeric values, never booleans.
 */
final class PineconeBooleanMembershipFilterTest extends TestCase {
	/** Valid portable boolean membership must fail closed before a vendor request is sent. */
	public function test_boolean_membership_filter_is_rejected_before_network(): void {
		$collection = new VectorCollection(
			'docs',
			new VectorIndexProfile(
				new EmbeddingProfile( 'openai-direct', 'model', 2, NormalizationMode::NONE ),
				DistanceMetric::COSINE
			)
		);
		$transport  = new QdrantFakeTransport( array() );
		$store      = new PineconeVectorStore(
			new PineconeConfig( 'https://docs-example.svc.us-east-1.pinecone.io', 'secret', 'docs-index' ),
			$collection->profile,
			$transport
		);
		$request    = new VectorSearchRequest(
			$collection,
			array( 1.0, 0.0 ),
			5,
			$collection->profile->fingerprint(),
			new InFilter( 'published', array( true, false ) )
		);

		try {
			$store->search( $request );
			self::fail( 'Unsupported Pinecone boolean membership must fail closed.' );
		} catch ( VectorStoreException $exception ) {
			self::assertSame( VectorStoreErrorCode::UNSUPPORTED_CAPABILITY, $exception->error_code );
		}

		self::assertCount( 0, $transport->requests );
	}
}
