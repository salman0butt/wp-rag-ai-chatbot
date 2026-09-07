<?php
/**
 * WordPress bot repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\TableNames;

/**
 * Persists independently addressable bot configurations with optimistic updates.
 */
final class WpdbBotRepository implements BotRepository {
	private const MAX_PAGE_SIZE = 100;

	/**
	 * Create the repository.
	 *
	 * @param Connection $connection Database connection.
	 * @param TableNames $tables Plugin table names.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Create one bot.
	 *
	 * @param string $name Human-readable bot name.
	 * @param bool   $enabled Whether the bot is enabled.
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 * @throws InvalidArgumentException When bot data is invalid.
	 * @throws DatabaseException When persistence fails.
	 */
	public function create( string $name, bool $enabled, string $provider_id, string $model_id ): Bot {
		$bot_id = new BotId( bin2hex( random_bytes( 16 ) ) );
		$now    = gmdate( 'Y-m-d H:i:s' );
		$bot    = new Bot( $bot_id, $name, $enabled, $provider_id, $model_id, 1, $now, $now );
		$result = $this->connection->insert(
			$this->tables->bots(),
			array(
				'bot_id'      => $bot->id->value,
				'name'        => $bot->name,
				'enabled'     => $bot->enabled ? 1 : 0,
				'provider_id' => $bot->provider_id,
				'model_id'    => $bot->model_id,
				'version'     => $bot->version,
				'created_at'  => $bot->created_at,
				'updated_at'  => $bot->updated_at,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			throw new DatabaseException( 'Could not create bot.' );
		}

		$persisted = $this->find( $bot_id );
		if ( null === $persisted ) {
			throw new DatabaseException( 'Created bot could not be reloaded.' );
		}

		return $persisted;
	}

	/**
	 * Find one bot by stable identifier.
	 *
	 * @param BotId $id Stable bot identifier.
	 */
	public function find( BotId $id ): ?Bot {
		$sql = $this->connection->prepare(
			'SELECT bot_id, name, enabled, provider_id, model_id, version, created_at, updated_at FROM %i WHERE bot_id = %s LIMIT 1',
			$this->tables->bots(),
			$id->value
		);
		$row = $this->connection->get_row( $sql );

		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Update one bot only when its expected version is current.
	 *
	 * @param BotId  $id Stable bot identifier.
	 * @param int    $expected_version Expected optimistic version.
	 * @param string $name Human-readable bot name.
	 * @param bool   $enabled Whether the bot is enabled.
	 * @param string $provider_id Provider identifier.
	 * @param string $model_id Model identifier.
	 * @throws InvalidArgumentException When bot data or the expected version is invalid.
	 * @throws RuntimeException When the update target is missing or stale.
	 * @throws DatabaseException When the updated row cannot be reloaded.
	 */
	public function update(
		BotId $id,
		int $expected_version,
		string $name,
		bool $enabled,
		string $provider_id,
		string $model_id
	): Bot {
		if ( $expected_version < 1 ) {
			throw new InvalidArgumentException( 'Expected bot version must be positive.' );
		}

		$now       = gmdate( 'Y-m-d H:i:s' );
		$candidate = new Bot( $id, $name, $enabled, $provider_id, $model_id, $expected_version + 1, $now, $now );
		$result    = $this->connection->update(
			$this->tables->bots(),
			array(
				'name'        => $candidate->name,
				'enabled'     => $candidate->enabled ? 1 : 0,
				'provider_id' => $candidate->provider_id,
				'model_id'    => $candidate->model_id,
				'version'     => $candidate->version,
				'updated_at'  => $candidate->updated_at,
			),
			array(
				'bot_id'  => $id->value,
				'version' => $expected_version,
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' ),
			array( '%s', '%d' )
		);

		if ( 1 !== $result ) {
			throw new RuntimeException( 'Bot update target is missing or stale.' );
		}

		$persisted = $this->find( $id );
		if ( null === $persisted ) {
			throw new DatabaseException( 'Updated bot could not be reloaded.' );
		}

		return $persisted;
	}

	/**
	 * Delete one bot by stable identifier.
	 *
	 * @param BotId $id Stable bot identifier.
	 * @throws DatabaseException When the delete query fails.
	 */
	public function delete( BotId $id ): bool {
		$result = $this->connection->delete(
			$this->tables->bots(),
			array( 'bot_id' => $id->value ),
			array( '%s' )
		);

		if ( false === $result ) {
			throw new DatabaseException( 'Could not delete bot.' );
		}

		return 1 === $result;
	}

	/**
	 * List a deterministic bounded page.
	 *
	 * @param int $page One-based page number.
	 * @param int $per_page Requested page size.
	 * @return array{items:array<int,Bot>,total:int,page:int,per_page:int}
	 * @throws InvalidArgumentException When pagination arguments are invalid.
	 */
	public function list( int $page, int $per_page ): array {
		if ( $page < 1 ) {
			throw new InvalidArgumentException( 'Bot page must be positive.' );
		}
		if ( $per_page < 1 || $per_page > self::MAX_PAGE_SIZE ) {
			throw new InvalidArgumentException( 'Bot page size is outside the allowed range.' );
		}

		$table  = $this->tables->bots();
		$total  = (int) $this->connection->get_var( "SELECT COUNT(*) FROM {$table}" );
		$offset = ( $page - 1 ) * $per_page;
		$sql    = $this->connection->prepare(
			"SELECT bot_id, name, enabled, provider_id, model_id, version, created_at, updated_at FROM {$table} ORDER BY created_at ASC, bot_id ASC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);
		$items  = array_map( fn ( array $row ): Bot => $this->hydrate( $row ), $this->connection->get_results( $sql ) );

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Rehydrate one persisted row.
	 *
	 * @param array<string,mixed> $row Persisted row.
	 */
	private function hydrate( array $row ): Bot {
		return new Bot(
			new BotId( (string) $row['bot_id'] ),
			(string) $row['name'],
			1 === (int) $row['enabled'],
			(string) $row['provider_id'],
			(string) $row['model_id'],
			(int) $row['version'],
			(string) $row['created_at'],
			(string) $row['updated_at']
		);
	}
}
