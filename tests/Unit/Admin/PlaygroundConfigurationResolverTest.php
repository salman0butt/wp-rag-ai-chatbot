<?php
/**
 * M13 request-local Playground configuration resolver tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Admin;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Admin\Rest\PlaygroundBotConfigurationResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundConfigurationResolver;
use WpRagAiChatbot\Admin\Rest\PlaygroundRetrievalConfigurationResolver;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

/**
 * Proves explicit persisted selectors are resolved into one closed Playground configuration.
 */
final class PlaygroundConfigurationResolverTest extends TestCase {
	/** Explicit bot/source/collection selectors must resolve through existing persistence boundaries. */
	public function test_resolves_explicit_persisted_configuration(): void {
		$bot               = $this->bot();
		$bot_repository    = $this->createMock( BotRepository::class );
		$source            = $this->source();
		$source_repository = $this->createMock( KnowledgeSourceRepository::class );
		$connection        = $this->connection();

		$bot_repository
			->expects( self::once() )
			->method( 'find' )
			->with( new BotId( $bot->id->value ) )
			->willReturn( $bot );
		$source_repository
			->expects( self::once() )
			->method( 'findById' )
			->with( 7 )
			->willReturn( $source );

		$resolver = new PlaygroundConfigurationResolver(
			new PlaygroundBotConfigurationResolver( $bot_repository ),
			new PlaygroundRetrievalConfigurationResolver(
				$source_repository,
				$connection,
				new TableNames( 'wp_' )
			)
		);

		$resolved = $resolver->resolve( $bot->id->value, 7, 'production-rag' );

		self::assertSame( $bot, $resolved->bot );
		self::assertSame( $source, $resolved->retrieval->source );
		self::assertSame( 'production-rag', $resolved->retrieval->collection_id );
	}

	/** Build one enabled persisted bot fixture. */
	private function bot(): Bot {
		return new Bot(
			new BotId( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ),
			'Production Bot',
			true,
			'openai',
			'gpt-4.1-mini',
			1,
			'2026-09-10 10:00:00',
			'2026-09-10 10:00:00'
		);
	}

	/** Build one persisted knowledge-source fixture. */
	private function source(): KnowledgeSourceRecord {
		$now = new DateTimeImmutable( '2026-09-10T10:00:00+00:00' );
		return new KnowledgeSourceRecord(
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
	}

	/** Build a connection double that resolves one explicit persisted collection. */
	private function connection(): Connection&MockObject {
		$connection = $this->createMock( Connection::class );
		$connection
			->expects( self::once() )
			->method( 'prepare' )
			->with(
				'SELECT collection_key FROM %i WHERE collection_key = %s LIMIT 1',
				'wp_rag_ai_vector_collections',
				'production-rag'
			)
			->willReturn( 'prepared-collection-query' );
		$connection
			->expects( self::once() )
			->method( 'get_var' )
			->with( 'prepared-collection-query' )
			->willReturn( 'production-rag' );
		return $connection;
	}
}
