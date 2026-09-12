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
}
