<?php
/**
 * M13 Playground vector collection resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundSemanticConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundVectorCollectionResolver;
use WpRagAiChatbot\Embeddings\DistanceMetric;
use WpRagAiChatbot\Embeddings\EmbeddingProfile;
use WpRagAiChatbot\Embeddings\NormalizationMode;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;

/**
 * Proves Playground collection identity reuses the canonical production vector profile.
 */
final class PlaygroundVectorCollectionResolverTest extends TestCase {
	/** Explicit persisted collection and semantic identity must reconstruct one canonical collection. */
	public function test_resolves_canonical_vector_collection(): void {
		$embedding = new EmbeddingProfile( 'openai', 'text-embedding-3-small', 1536, NormalizationMode::L2 );
		$semantic  = new PlaygroundSemanticConfiguration( $embedding, DistanceMetric::COSINE, 'local-wordpress' );
		$now       = new DateTimeImmutable( '2026-09-10T10:00:00+00:00' );
		$source    = new KnowledgeSourceRecord(
			7,
			'wordpress-posts',
			'wordpress_posts',
			null,
			'WordPress Posts',
			null,
			'indexed',
			array(),
			null,
			$now,
			$now,
			$now
		);
		$retrieval = new PlaygroundRetrievalConfiguration( $source, 'production-rag' );

		$collection = ( new PlaygroundVectorCollectionResolver() )->resolve( $retrieval, $semantic );

		self::assertSame( 'production-rag', $collection->id );
		self::assertSame( $embedding, $collection->profile->embedding );
		self::assertSame( DistanceMetric::COSINE, $collection->profile->distance );
	}
}
