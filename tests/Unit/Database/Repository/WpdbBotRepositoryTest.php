<?php
/**
 * M12 persisted bot repository tests.
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
use WpRagAiChatbot\Database\Repository\WpdbBotRepository;
use WpRagAiChatbot\Database\TableNames;

/**
 * Proves isolated CRUD, optimistic versioning, and bounded pagination.
 */
final class WpdbBotRepositoryTest extends TestCase {
	/** Creation persists an opaque stable identifier and rehydrates only that bot. */
	public function test_create_inserts_and_rehydrates_one_bot(): void {
		self::assertTrue( class_exists( WpdbBotRepository::class ), 'M12 Task 2 requires WpdbBotRepository.' );
		$connection = $this->connection();
		$bot_id     = null;
		$connection->expects( self::once() )->method( 'insert' )->willReturnCallback(
			static function ( string $table, array $data ) use ( &$bot_id ): int {
				self::assertSame( 'wp_rag_ai_bots', $table );
				$bot_id = $data['bot_id'] ?? null;
				self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', (string) $bot_id );
				self::assertSame( 'Support Bot', $data['name'] ?? null );
				self::assertSame( 1, $data['enabled'] ?? null );
				self::assertSame( 'openai', $data['provider_id'] ?? null );
				self::assertSame( 'gpt-5-mini', $data['model_id'] ?? null );
				self::assertSame( 1, $data['version'] ?? null );
				return 1;
			}
		);
		$connection->method( 'prepare' )->willReturn( 'bot-row' );
		$connection->method( 'get_row' )->willReturnCallback(
			static fn (): array => self::row( (string) $bot_id, 'Support Bot', 1, 'openai', 'gpt-5-mini', 1 )
		);

		$bot = $this->repository( $connection )->create( 'Support Bot', true, 'openai', 'gpt-5-mini' );

		self::assertSame( $bot_id, $bot->id->value );
		self::assertSame( 1, $bot->version );
	}

	/** Reads are scoped to the exact bot identifier. */
	public function test_find_uses_exact_bot_identifier(): void {
		self::assertTrue( class_exists( WpdbBotRepository::class ), 'M12 Task 2 requires WpdbBotRepository.' );
		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertStringContainsString( 'WHERE bot_id = %s', $query );
				self::assertContains( '0123456789abcdef0123456789abcdef', $args );
				return 'bot-row';
			}
		);
		$connection->method( 'get_row' )->willReturn( self::row( '0123456789abcdef0123456789abcdef' ) );

		$bot = $this->repository( $connection )->find( new BotId( '0123456789abcdef0123456789abcdef' ) );
		self::assertSame( '0123456789abcdef0123456789abcdef', $bot?->id->value );
	}

	/** Updates include the expected version so stale writes fail closed. */
	public function test_update_uses_optimistic_version_and_increments_version(): void {
		self::assertTrue( class_exists( WpdbBotRepository::class ), 'M12 Task 2 requires WpdbBotRepository.' );
		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'update' )->willReturnCallback(
			static function ( string $table, array $data, array $where ): int {
				self::assertSame( 'wp_rag_ai_bots', $table );
				self::assertSame( 4, $data['version'] ?? null );
				self::assertSame(
					array(
						'bot_id'  => '0123456789abcdef0123456789abcdef',
						'version' => 3,
					),
					$where
				);
				return 1;
			}
		);
		$connection->method( 'prepare' )->willReturn( 'updated-row' );
		$connection->method( 'get_row' )->willReturn( self::row( '0123456789abcdef0123456789abcdef', 'Updated Bot', 0, 'openai', 'gpt-5-mini', 4 ) );

		$bot = $this->repository( $connection )->update(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			3,
			'Updated Bot',
			false,
			'openai',
			'gpt-5-mini'
		);
		self::assertSame( 4, $bot->version );
		self::assertFalse( $bot->enabled );
	}

	/** A stale version cannot silently overwrite a newer bot. */
	public function test_update_rejects_stale_version(): void {
		self::assertTrue( class_exists( WpdbBotRepository::class ), 'M12 Task 2 requires WpdbBotRepository.' );
		$connection = $this->connection();
		$connection->method( 'update' )->willReturn( 0 );

		$this->expectException( RuntimeException::class );
		$this->repository( $connection )->update(
			new BotId( '0123456789abcdef0123456789abcdef' ),
			2,
			'Updated Bot',
			true,
			'openai',
			'gpt-5-mini'
		);
	}

	/** Delete affects exactly one identifier and reports missing targets deterministically. */
	public function test_delete_uses_exact_identifier(): void {
		self::assertTrue( class_exists( WpdbBotRepository::class ), 'M12 Task 2 requires WpdbBotRepository.' );
		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'delete' )->with(
			'wp_rag_ai_bots',
			array( 'bot_id' => '0123456789abcdef0123456789abcdef' ),
			array( '%s' )
		)->willReturn( 1 );

		self::assertTrue( $this->repository( $connection )->delete( new BotId( '0123456789abcdef0123456789abcdef' ) ) );
	}

	/** Pagination is deterministic and hard-bounded. */
	public function test_list_returns_bounded_page_and_total(): void {
		self::assertTrue( class_exists( WpdbBotRepository::class ), 'M12 Task 2 requires WpdbBotRepository.' );
		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'get_var' )->willReturn( 2 );
		$connection->expects( self::once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertStringContainsString( 'FROM %i', $query );
				self::assertStringContainsString( 'ORDER BY created_at ASC, bot_id ASC', $query );
				self::assertStringContainsString( 'LIMIT %d OFFSET %d', $query );
				self::assertSame( array( 'wp_rag_ai_bots', 10, 0 ), $args );
				return 'page';
			}
		);
		$connection->expects( self::once() )->method( 'get_results' )->with( 'page' )->willReturn(
			array(
				self::row( '0123456789abcdef0123456789abcdef', 'One' ),
				self::row( 'fedcba9876543210fedcba9876543210', 'Two' ),
			)
		);

		$page = $this->repository( $connection )->list( 1, 10 );
		self::assertSame( 2, $page['total'] );
		self::assertCount( 2, $page['items'] );
		self::assertSame( 1, $page['page'] );
		self::assertSame( 10, $page['per_page'] );
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
	private function repository( Connection $connection ): WpdbBotRepository {
		return new WpdbBotRepository( $connection, new TableNames( 'wp_' ) );
	}

	/**
	 * Build one persisted bot row.
	 *
	 * @param string $bot_id Stable bot identifier.
	 * @param string $name Bot name.
	 * @param int    $enabled Enabled storage flag.
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 * @param int    $version Persisted optimistic version.
	 * @return array<string,mixed>
	 */
	private static function row(
		string $bot_id,
		string $name = 'Support Bot',
		int $enabled = 1,
		string $provider_id = 'openai',
		string $model_id = 'gpt-5-mini',
		int $version = 1
	): array {
		return array(
			'bot_id'      => $bot_id,
			'name'        => $name,
			'enabled'     => $enabled,
			'provider_id' => $provider_id,
			'model_id'    => $model_id,
			'version'     => $version,
			'created_at'  => '2026-09-07 06:00:00',
			'updated_at'  => '2026-09-07 06:00:00',
		);
	}
}
