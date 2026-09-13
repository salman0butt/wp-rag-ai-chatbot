<?php
/**
 * WordPress bot retrieval binding repository tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database\Repository;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbBotRetrievalBindingRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;

/** Specifies persistence of bot-scoped public retrieval authority. */
final class WpdbBotRetrievalBindingRepositoryTest extends TestCase {
	/** Persisted source/collection values are projected as one bounded binding. */
	public function test_finds_persisted_binding(): void {
		self::assertTrue( class_exists( WpdbBotRetrievalBindingRepository::class ), 'WpdbBotRetrievalBindingRepository is missing.' );

		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )->method( 'prepare' )->willReturn( 'prepared' );
		$connection->expects( self::once() )->method( 'get_row' )->with( 'prepared' )->willReturn(
			array(
				'retrieval_source_id'     => '42',
				'retrieval_collection_id' => 'support-en-v1',
			)
		);

		$binding = ( new WpdbBotRetrievalBindingRepository( $connection, new TableNames( 'wp_' ) ) )->find(
			new BotId( '0123456789abcdef0123456789abcdef' )
		);

		self::assertInstanceOf( BotRetrievalBinding::class, $binding );
		self::assertSame( 42, $binding->source_id );
		self::assertSame( 'support-en-v1', $binding->collection_id );
	}

	/** Existing bots without a configured binding remain safely unconfigured. */
	public function test_returns_null_for_unconfigured_bot(): void {
		self::assertTrue( class_exists( WpdbBotRetrievalBindingRepository::class ), 'WpdbBotRetrievalBindingRepository is missing.' );

		$connection = $this->createMock( Connection::class );
		$connection->method( 'prepare' )->willReturn( 'prepared' );
		$connection->method( 'get_row' )->willReturn(
			array(
				'retrieval_source_id'     => null,
				'retrieval_collection_id' => null,
			)
		);

		self::assertNull(
			( new WpdbBotRetrievalBindingRepository( $connection, new TableNames( 'wp_' ) ) )->find(
				new BotId( '0123456789abcdef0123456789abcdef' )
			)
		);
	}

	/** Persistence writes only the trusted server-owned retrieval selection. */
	public function test_saves_binding_to_existing_bot_row(): void {
		self::assertTrue( class_exists( WpdbBotRetrievalBindingRepository::class ), 'WpdbBotRetrievalBindingRepository is missing.' );

		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )
			->method( 'update' )
			->with(
				'wp_rag_ai_bots',
				array(
					'retrieval_source_id'     => 42,
					'retrieval_collection_id' => 'support-en-v1',
				),
				array( 'bot_id' => '0123456789abcdef0123456789abcdef' ),
				array( '%d', '%s' ),
				array( '%s' )
			)
			->willReturn( 1 );

		( new WpdbBotRetrievalBindingRepository( $connection, new TableNames( 'wp_' ) ) )->save(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			new BotRetrievalBinding( 42, 'support-en-v1' )
		);
	}

	/** Invalid persisted values fail closed rather than creating an unsafe runtime scope. */
	public function test_invalid_persisted_binding_fails_closed(): void {
		self::assertTrue( class_exists( WpdbBotRetrievalBindingRepository::class ), 'WpdbBotRetrievalBindingRepository is missing.' );

		$connection = $this->createMock( Connection::class );
		$connection->method( 'prepare' )->willReturn( 'prepared' );
		$connection->method( 'get_row' )->willReturn(
			array(
				'retrieval_source_id'     => '0',
				'retrieval_collection_id' => '../unsafe',
			)
		);

		$this->expectException( RuntimeException::class );
		( new WpdbBotRetrievalBindingRepository( $connection, new TableNames( 'wp_' ) ) )->find(
			new BotId( '0123456789abcdef0123456789abcdef' )
		);
	}
}
