<?php
/**
 * M14 bot-scoped appearance persistence tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database\Repository;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbBotAppearanceRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\AppearanceConfig;
use WpRagAiChatbot\Frontend\BotAppearanceRepository;

/** Proves appearance persistence is bot-scoped and normalized. */
final class WpdbBotAppearanceRepositoryTest extends TestCase {
	/** A legacy bot row with no stored appearance resolves bounded defaults. */
	public function test_find_projects_defaults_for_legacy_null_appearance(): void {
		self::assertTrue(
			interface_exists( BotAppearanceRepository::class ),
			'M14 Task 2A requires BotAppearanceRepository.'
		);
		self::assertTrue(
			class_exists( WpdbBotAppearanceRepository::class ),
			'M14 Task 2A requires WpdbBotAppearanceRepository.'
		);

		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertStringContainsString( 'SELECT appearance_json FROM %i WHERE bot_id = %s', $query );
				self::assertSame( array( 'wp_rag_ai_bots', '0123456789abcdef0123456789abcdef' ), $args );
				return 'appearance-row';
			}
		);
		$connection->expects( self::once() )->method( 'get_row' )->with( 'appearance-row' )->willReturn(
			array( 'appearance_json' => null )
		);

		$appearance = $this->repository( $connection )->find( new BotId( '0123456789abcdef0123456789abcdef' ) );
		self::assertSame( AppearanceConfig::defaults()->to_array(), $appearance->to_array() );
	}

	/** Persisted JSON must re-enter the bounded AppearanceConfig authority. */
	public function test_find_rehydrates_only_normalized_appearance(): void {
		self::assertTrue(
			class_exists( WpdbBotAppearanceRepository::class ),
			'M14 Task 2A requires WpdbBotAppearanceRepository.'
		);
		$connection = $this->connection();
		$connection->method( 'prepare' )->willReturn( 'appearance-row' );
		$connection->method( 'get_row' )->willReturn(
			array(
				'appearance_json' => '{"primary_color":"#ABCDEF","color_mode":"dark",'
					. '"position":"bottom-left","launcher_style":"icon","panel_size":"large",'
					. '"radius_px":20,"font_family":"sans"}',
			)
		);

		$appearance = $this->repository( $connection )->find( new BotId( '0123456789abcdef0123456789abcdef' ) );
		self::assertSame( '#abcdef', $appearance->primary_color );
		self::assertSame( 'dark', $appearance->color_mode );
		self::assertSame( 'bottom-left', $appearance->position );
	}

	/** Corrupted persistence fails closed instead of leaking or accepting raw values. */
	public function test_find_rejects_missing_or_malformed_persisted_state(): void {
		self::assertTrue(
			class_exists( WpdbBotAppearanceRepository::class ),
			'M14 Task 2A requires WpdbBotAppearanceRepository.'
		);
		$connection = $this->connection();
		$connection->method( 'prepare' )->willReturn( 'appearance-row' );
		$connection->method( 'get_row' )->willReturn( null );

		$this->expectException( RuntimeException::class );
		$this->repository( $connection )->find( new BotId( '0123456789abcdef0123456789abcdef' ) );
	}

	/** Save writes only the normalized appearance projection for the exact bot. */
	public function test_save_writes_only_normalized_appearance_for_exact_bot(): void {
		self::assertTrue(
			class_exists( WpdbBotAppearanceRepository::class ),
			'M14 Task 2A requires WpdbBotAppearanceRepository.'
		);
		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'update' )->willReturnCallback(
			static function ( string $table, array $data, array $where, array $format, array $where_format ): int {
				self::assertSame( 'wp_rag_ai_bots', $table );
				self::assertSame( array( 'bot_id' => '0123456789abcdef0123456789abcdef' ), $where );
				self::assertSame( array( '%s' ), $format );
				self::assertSame( array( '%s' ), $where_format );
				self::assertSame( array( 'appearance_json' ), array_keys( $data ) );
				$decoded = json_decode( (string) $data['appearance_json'], true, 512, JSON_THROW_ON_ERROR );
				self::assertSame( '#abcdef', $decoded['primary_color'] ?? null );
				self::assertArrayNotHasKey( 'provider_id', $decoded );
				self::assertArrayNotHasKey( 'model_id', $decoded );
				return 1;
			}
		);

		$this->repository( $connection )->save(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			AppearanceConfig::from_array( array( 'primary_color' => '#ABCDEF' ) )
		);
	}

	/**
	 * Create a connection mock.
	 *
	 * @return Connection&MockObject
	 */
	private function connection(): Connection {
		return $this->createMock( Connection::class );
	}

	/**
	 * Create the repository under test.
	 *
	 * @param Connection $connection Mocked persistence connection.
	 */
	private function repository( Connection $connection ): WpdbBotAppearanceRepository {
		return new WpdbBotAppearanceRepository( $connection, new TableNames( 'wp_' ) );
	}
}
