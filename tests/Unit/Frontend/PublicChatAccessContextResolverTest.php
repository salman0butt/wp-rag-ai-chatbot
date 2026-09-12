<?php
/**
 * Public chat access-context resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\PublicChatAccessContextResolver;
use WpRagAiChatbot\Frontend\PublicChatRuntime;
use WpRagAiChatbot\Retrieval\Lexical\ChunkLookupStore;

/**
 * Specifies trusted persisted retrieval scope for public production chat.
 */
final class PublicChatAccessContextResolverTest extends TestCase {
	/** Persisted bot/source/collection authority becomes the exact M11 access context. */
	public function test_resolves_public_access_context_from_persisted_runtime(): void {
		self::assertTrue( class_exists( PublicChatAccessContextResolver::class ), 'PublicChatAccessContextResolver is missing.' );

		$chunks  = $this->createMock( ChunkLookupStore::class );
		$runtime = new PublicChatRuntime(
			new Bot(
				new BotId( '0123456789abcdef0123456789abcdef' ),
				'Support bot',
				true,
				'openai',
				'gpt-4.1-mini',
				1,
				'2026-09-12 00:00:00',
				'2026-09-12 00:00:00'
			),
			new BotRetrievalBinding( 42, 'support-en-v1' )
		);

		$context = ( new PublicChatAccessContextResolver( $chunks ) )->resolve( $runtime );

		self::assertSame( 'public:0123456789abcdef0123456789abcdef', $context->owner_scope );
		self::assertSame( 'support-en-v1', $context->lexical_filter->collection_id );
		self::assertSame( 42, $context->lexical_filter->source_id );
		self::assertFalse( $context->allow_single_channel_degradation );

		$chunks->expects( self::once() )
			->method( 'find_chunk' )
			->with( 'support-en-v1', 'chunk-1' )
			->willReturn( null );

		self::assertNull( $context->semantic_context->resolve_chunk( 'chunk-1' ) );
	}
}
