<?php
/**
 * WordPress bot retrieval binding repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use InvalidArgumentException;
use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\BotRetrievalBinding;
use WpRagAiChatbot\Frontend\BotRetrievalBindingRepository;

/**
 * Persists the trusted public retrieval selection in the existing bot row.
 */
final class WpdbBotRetrievalBindingRepository implements BotRetrievalBindingRepository {
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
	 * Load one persisted binding.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 * @throws RuntimeException When persisted retrieval authority is malformed.
	 */
	public function find( BotId $bot_id ): ?BotRetrievalBinding {
		$sql = $this->connection->prepare(
			'SELECT retrieval_source_id, retrieval_collection_id FROM %i WHERE bot_id = %s LIMIT 1',
			$this->tables->bots(),
			$bot_id->value
		);
		$row = $this->connection->get_row( $sql );

		if ( null === $row ) {
			return null;
		}

		$source_id     = $row['retrieval_source_id'] ?? null;
		$collection_id = $row['retrieval_collection_id'] ?? null;

		if ( null === $source_id && null === $collection_id ) {
			return null;
		}
		if ( ! is_string( $collection_id ) || ( ! is_int( $source_id ) && ! is_string( $source_id ) ) ) {
			throw new RuntimeException( 'Persisted bot retrieval binding is invalid.' );
		}
		if ( is_string( $source_id ) && ( '' === $source_id || ! ctype_digit( $source_id ) ) ) {
			throw new RuntimeException( 'Persisted bot retrieval binding is invalid.' );
		}

		try {
			return new BotRetrievalBinding( (int) $source_id, $collection_id );
		} catch ( InvalidArgumentException ) {
			throw new RuntimeException( 'Persisted bot retrieval binding is invalid.' );
		}
	}

	/**
	 * Return valid bindings for the requested bot IDs in one query.
	 *
	 * @param array<int,BotId> $bot_ids Stable bot identifiers.
	 * @return array<string,BotRetrievalBinding> Bindings keyed by bot ID.
	 */
	public function find_for_bot_ids( array $bot_ids ): array {
		if ( array() === $bot_ids ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $bot_ids ), '%s' ) );
		$args         = array( $this->tables->bots() );
		foreach ( $bot_ids as $bot_id ) {
			$args[] = $bot_id->value;
		}

		$sql      = $this->connection->prepare(
			'SELECT bot_id, retrieval_source_id, retrieval_collection_id FROM %i WHERE bot_id IN (' . $placeholders . ') AND retrieval_source_id IS NOT NULL AND retrieval_collection_id IS NOT NULL',
			...$args
		);
		$bindings = array();
		foreach ( $this->connection->get_results( $sql ) as $row ) {
			try {
				$bot_id        = new BotId( (string) ( $row['bot_id'] ?? '' ) );
				$source_id     = $row['retrieval_source_id'] ?? null;
				$collection_id = $row['retrieval_collection_id'] ?? null;
				if ( ! is_string( $collection_id ) || ( ! is_int( $source_id ) && ! is_string( $source_id ) ) ) {
					continue;
				}
				if ( is_string( $source_id ) && ( '' === $source_id || ! ctype_digit( $source_id ) ) ) {
					continue;
				}
				$bindings[ $bot_id->value ] = new BotRetrievalBinding( (int) $source_id, $collection_id );
			} catch ( InvalidArgumentException ) {
				continue;
			}
		}

		return $bindings;
	}

	/**
	 * Determine whether any bot row has both binding columns populated.
	 */
	public function has_any(): bool {
		$sql = $this->connection->prepare(
			'SELECT 1 FROM %i WHERE retrieval_source_id IS NOT NULL AND retrieval_collection_id IS NOT NULL LIMIT 1',
			$this->tables->bots()
		);

		return null !== $this->connection->get_var( $sql );
	}

	/**
	 * Persist one trusted binding.
	 *
	 * @param BotId               $bot_id Stable bot identifier.
	 * @param BotRetrievalBinding $binding Trusted retrieval selection.
	 * @throws RuntimeException When the target bot is missing or persistence fails.
	 */
	public function save( BotId $bot_id, BotRetrievalBinding $binding ): void {
		$result = $this->connection->update(
			$this->tables->bots(),
			array(
				'retrieval_source_id'     => $binding->source_id,
				'retrieval_collection_id' => $binding->collection_id,
			),
			array( 'bot_id' => $bot_id->value ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		if ( 1 !== $result ) {
			throw new RuntimeException( 'Bot retrieval binding target is missing or could not be updated.' );
		}
	}

	/**
	 * Clear only the retrieval binding columns on one existing bot row.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 * @throws RuntimeException When the database update fails.
	 */
	public function clear( BotId $bot_id ): void {
		$result = $this->connection->update(
			$this->tables->bots(),
			array(
				'retrieval_source_id'     => null,
				'retrieval_collection_id' => null,
			),
			array( 'bot_id' => $bot_id->value ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		if ( false === $result ) {
			throw new RuntimeException( 'Bot retrieval binding could not be cleared.' );
		}
	}
}
