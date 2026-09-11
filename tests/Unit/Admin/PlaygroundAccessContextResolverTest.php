<?php
/**
 * Playground access-context composition tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use WpRagAiChatbot\Admin\Rest\PlaygroundConfiguration;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfiguration;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Chat\ChatAccessContext;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Retrieval\Lexical\ChunkLookupStore;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;

/**
 * Specifies trusted persisted source/collection scoping for Playground chat execution.
 */
final class PlaygroundAccessContextResolverTest extends TestCase {
	/**
	 * The resolver must derive both retrieval channels from persisted selection only.
	 */
	public function test_resolves_source_scoped_access_context_from_persisted_configuration(): void {
		$resolver_class = 'WpRagAiChatbot\\Admin\\Rest\\PlaygroundAccessContextResolver';
		self::assertTrue( class_exists( $resolver_class ), 'PlaygroundAccessContextResolver is missing.' );

		$chunk         = new ChunkSearchRecord(
			str_repeat( 'b', 64 ),
			'document-key',
			8,
			'post',
			'Refund policy',
			'https://example.com/refunds',
			'Refunds are available within 30 days.',
			str_repeat( 'c', 64 ),
			'en',
			'public',
			0
		);
		$store         = new class( $chunk ) implements ChunkLookupStore {
			/**
			 * Create the deterministic chunk lookup fake.
			 *
			 * @param ChunkSearchRecord $chunk Persisted chunk fixture.
			 */
			public function __construct( private readonly ChunkSearchRecord $chunk ) {
			}

			/**
			 * Resolve the configured chunk inside its expected collection.
			 *
			 * @param string $collection_id Explicit persisted collection scope.
			 * @param string $chunk_key Stable lowercase SHA-256 chunk key.
			 */
			public function find_chunk( string $collection_id, string $chunk_key ): ?ChunkSearchRecord {
				return 'collection-1' === $collection_id && $this->chunk->chunk_key === $chunk_key
					? $this->chunk
					: null;
			}
		};
		$source        = new KnowledgeSourceRecord(
			8,
			'source-key',
			'web',
			null,
			'Refunds',
			null,
			'active',
			array(),
			null,
			null,
			new DateTimeImmutable( '2026-09-11T00:00:00+00:00' ),
			new DateTimeImmutable( '2026-09-11T00:00:00+00:00' )
		);
		$configuration = new PlaygroundConfiguration(
			new Bot(
				new BotId( str_repeat( 'a', 32 ) ),
				'Playground bot',
				true,
				'openai',
				'gpt-test',
				1,
				'2026-09-11 00:00:00',
				'2026-09-11 00:00:00'
			),
			new PlaygroundRetrievalConfiguration( $source, 'collection-1' )
		);

		$resolver = ( new ReflectionClass( $resolver_class ) )->newInstance( $store );
		$access   = ( new ReflectionMethod( $resolver_class, 'resolve' ) )->invoke( $resolver, $configuration );

		self::assertInstanceOf( ChatAccessContext::class, $access );
		self::assertSame( 'playground:' . str_repeat( 'a', 32 ), $access->owner_scope );
		self::assertSame( array( 8 ), $access->semantic_context->filter->source_ids );
		self::assertSame( 'collection-1', $access->lexical_filter->collection_id );
		self::assertSame( 8, $access->lexical_filter->source_id );
		self::assertFalse( $access->allow_single_channel_degradation );
		self::assertSame( $chunk, $access->semantic_context->resolve_chunk( str_repeat( 'b', 64 ) ) );
	}
}
