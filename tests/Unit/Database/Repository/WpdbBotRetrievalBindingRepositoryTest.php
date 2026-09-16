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

	/** Clearing a binding nulls only the two retrieval columns on the bot row. */
	public function test_clears_only_retrieval_binding_columns(): void {
		self::assertTrue( class_exists( WpdbBotRetrievalBindingRepository::class ), 'WpdbBotRetrievalBindingRepository is missing.' );

		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )
			->method( 'update' )
			->with(
				'wp_rag_ai_bots',
				array(
					'retrieval_source_id'     => null,
					'retrieval_collection_id' => null,
				),
				array( 'bot_id' => '0123456789abcdef0123456789abcdef' ),
				array( '%d', '%s' ),
				array( '%s' )
			)
			->willReturn( 1 );

		( new WpdbBotRetrievalBindingRepository( $connection, new TableNames( 'wp_' ) ) )->clear(
			new BotId( '0123456789abcdef0123456789abcdef' )
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

	/** Binding existence is answered by one scalar repository query. */
	public function test_has_any_uses_an_existence_query(): void {
		self::assertTrue( class_exists( WpdbBotRetrievalBindingRepository::class ), 'WpdbBotRetrievalBindingRepository is missing.' );
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )->method( 'prepare' )->willReturn( 'prepared' );
		$connection->expects( self::once() )->method( 'get_var' )->with( 'prepared' )->willReturn( 1 );

		self::assertTrue( ( new WpdbBotRetrievalBindingRepository( $connection, new TableNames( 'wp_' ) ) )->has_any() );
	}

	/** Batch binding lookup returns only valid rows for the requested bot IDs. */
	public function test_find_for_bot_ids_uses_one_bounded_query_and_skips_malformed_rows(): void {
		self::assertTrue( class_exists( WpdbBotRetrievalBindingRepository::class ), 'WpdbBotRetrievalBindingRepository is missing.' );
		$connection = $this->createMock( Connection::class );
		$connection->expects( self::once() )
			->method( 'prepare' )
			->willReturnCallback(
				static function ( string $query, mixed ...$args ): string {
					self::assertStringContainsString( 'bot_id IN (%s, %s)', $query );
					self::assertSame(
						array( 'wp_rag_ai_bots', '0123456789abcdef0123456789abcdef', 'fedcba9876543210fedcba9876543210' ),
						$args
					);
					return 'prepared';
				}
			);
		$connection->expects( self::once() )->method( 'get_results' )->with( 'prepared' )->willReturn(
			array(
				array(
					'bot_id'                  => '0123456789abcdef0123456789abcdef',
					'retrieval_source_id'     => '42',
					'retrieval_collection_id' => 'support-en-v1',
				),
				array(
					'bot_id'                  => 'fedcba9876543210fedcba9876543210',
					'retrieval_source_id'     => 'bad',
					'retrieval_collection_id' => 'support-en-v1',
				),
			)
		);

		$bindings = ( new WpdbBotRetrievalBindingRepository( $connection, new TableNames( 'wp_' ) ) )->find_for_bot_ids(
			array(
				new BotId( '0123456789abcdef0123456789abcdef' ),
				new BotId( 'fedcba9876543210fedcba9876543210' ),
			)
		);

		self::assertCount( 1, $bindings );
		self::assertSame( 42, $bindings['0123456789abcdef0123456789abcdef']->source_id );
	}
}
