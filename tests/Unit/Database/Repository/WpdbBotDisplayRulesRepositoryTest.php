<?php
/**
 * M15 bot-scoped display-rules persistence tests.
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
use WpRagAiChatbot\Database\Repository\WpdbBotDisplayRulesRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\BotDisplayRulesRepository;
use WpRagAiChatbot\Frontend\DisplayRulesConfig;

/** Proves display-rule persistence remains normalized and isolated per bot. */
final class WpdbBotDisplayRulesRepositoryTest extends TestCase {
	/** Existing bots without persisted M15 rules resolve deterministic defaults. */
	public function test_find_projects_defaults_for_existing_bot_without_display_rules(): void {
		self::assertTrue(
			interface_exists( BotDisplayRulesRepository::class ),
			'M15 Task 2B requires BotDisplayRulesRepository.'
		);
		self::assertTrue(
			class_exists( WpdbBotDisplayRulesRepository::class ),
			'M15 Task 2B requires WpdbBotDisplayRulesRepository.'
		);

		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertStringContainsString( 'SELECT display_rules_json FROM %i WHERE bot_id = %s', $query );
				self::assertSame( array( 'wp_rag_ai_bots', '0123456789abcdef0123456789abcdef' ), $args );
				return 'display-rules-row';
			}
		);
		$connection->expects( self::once() )->method( 'get_row' )->with( 'display-rules-row' )->willReturn(
			array( 'display_rules_json' => null )
		);

		$config = $this->repository( $connection )->find( new BotId( '0123456789abcdef0123456789abcdef' ) );
		self::assertSame( DisplayRulesConfig::defaults()->to_array(), $config->to_array() );
	}

	/** Saved JSON must re-enter the immutable DisplayRulesConfig normalization authority. */
	public function test_find_rehydrates_normalized_display_rules_for_exact_bot(): void {
		$connection = $this->connection();
		$connection->method( 'prepare' )->willReturn( 'display-rules-row' );
		$connection->method( 'get_row' )->willReturn(
			array(
				'display_rules_json' => '{"enabled":false,"visibility":{"url_include":[" /pricing/* "]}}',
			)
		);

		$config = $this->repository( $connection )->find( new BotId( 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' ) );
		self::assertFalse( $config->enabled );
		self::assertSame( array( '/pricing/*' ), $config->to_array()['visibility']['url_include'] ?? null );
	}

	/** Save writes one normalized JSON projection scoped to the supplied bot id. */
	public function test_save_writes_only_normalized_display_rules_for_exact_bot(): void {
		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'update' )->willReturnCallback(
			static function ( string $table, array $data, array $where, array $format, array $where_format ): int {
				self::assertSame( 'wp_rag_ai_bots', $table );
				self::assertSame( array( 'bot_id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ), $where );
				self::assertSame( array( '%s' ), $format );
				self::assertSame( array( '%s' ), $where_format );
				self::assertSame( array( 'display_rules_json' ), array_keys( $data ) );
				$decoded = json_decode( (string) $data['display_rules_json'], true, 512, JSON_THROW_ON_ERROR );
				self::assertFalse( $decoded['enabled'] ?? true );
				self::assertArrayNotHasKey( 'provider_id', $decoded );
				self::assertArrayNotHasKey( 'model_id', $decoded );
				return 1;
			}
		);

		$this->repository( $connection )->save(
			new BotId( 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' ),
			DisplayRulesConfig::from_array( array( 'enabled' => false ) )
		);
	}

	/** Once the bot row is deleted, reads fail rather than leaking another bot's rules. */
	public function test_find_fails_after_bot_deletion(): void {
		$connection = $this->connection();
		$connection->method( 'prepare' )->willReturn( 'display-rules-row' );
		$connection->method( 'get_row' )->willReturn( null );

		$this->expectException( RuntimeException::class );
		$this->repository( $connection )->find( new BotId( 'cccccccccccccccccccccccccccccccc' ) );
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
	private function repository( Connection $connection ): WpdbBotDisplayRulesRepository {
		return new WpdbBotDisplayRulesRepository( $connection, new TableNames( 'wp_' ) );
	}
}
