<?php
/**
 * Public chat knowledge-source resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Frontend;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\PublicChatKnowledgeSourceResolver;
use WpRagAiChatbot\Frontend\PublicChatRuntime;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/**
 * Specifies persisted knowledge-source recovery for production public chat.
 */
final class PublicChatKnowledgeSourceResolverTest extends TestCase {
	/** The exact source selected by the persisted bot binding is recovered server-side. */
	public function test_resolves_bound_persisted_knowledge_source(): void {
		self::assertTrue( class_exists( PublicChatKnowledgeSourceResolver::class ), 'PublicChatKnowledgeSourceResolver is missing.' );

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
		$source  = new KnowledgeSourceRecord(
			42,
			'support-docs',
			'url',
			null,
			'Support docs',
			'https://example.test/support',
			'active',
			array( 'semantic_retrieval' => array( 'embedding_provider_id' => 'openai' ) ),
			null,
			null,
			new DateTimeImmutable( '2026-09-12 00:00:00' ),
			new DateTimeImmutable( '2026-09-12 00:00:00' )
		);

		$sources = $this->createMock( KnowledgeSourceRepository::class );
		$sources->expects( self::once() )->method( 'findById' )->with( 42 )->willReturn( $source );

		self::assertSame( $source, ( new PublicChatKnowledgeSourceResolver( $sources ) )->resolve( $runtime ) );
	}
}
