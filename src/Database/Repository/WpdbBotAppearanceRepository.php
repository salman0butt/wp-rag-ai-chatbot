<?php
/**
 * WordPress bot appearance repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use JsonException;
use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Frontend\AppearanceConfig;
use WpRagAiChatbot\Frontend\BotAppearanceRepository;

/** Persists normalized appearance in the existing bot row. */
final class WpdbBotAppearanceRepository implements BotAppearanceRepository {
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
	 * Load normalized appearance for one bot.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 * @throws RuntimeException When the bot is missing or persisted appearance is invalid.
	 */
	public function find( BotId $bot_id ): AppearanceConfig {
		$sql = $this->connection->prepare(
			'SELECT appearance_json FROM %i WHERE bot_id = %s LIMIT 1',
			$this->tables->bots(),
			$bot_id->value
		);
		$row = $this->connection->get_row( $sql );

		if ( null === $row ) {
			throw new RuntimeException( 'Bot appearance target was not found.' );
		}

		$encoded = $row['appearance_json'] ?? null;
		if ( null === $encoded ) {
			return AppearanceConfig::defaults();
		}
		if ( ! is_string( $encoded ) || '' === trim( $encoded ) ) {
			throw new RuntimeException( 'Persisted bot appearance is invalid.' );
		}

		try {
			$decoded = json_decode( $encoded, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			throw new RuntimeException( 'Persisted bot appearance is invalid.', 0, $exception );
		}

		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( 'Persisted bot appearance is invalid.' );
		}

		try {
			return AppearanceConfig::from_array( $decoded );
		} catch ( \InvalidArgumentException $exception ) {
			throw new RuntimeException( 'Persisted bot appearance is invalid.', 0, $exception );
		}
	}

	/**
	 * Persist normalized appearance for one bot.
	 *
	 * @param BotId            $bot_id Stable bot identifier.
	 * @param AppearanceConfig $appearance Normalized appearance.
	 * @throws RuntimeException When the target bot does not exist or persistence fails.
	 */
	public function save( BotId $bot_id, AppearanceConfig $appearance ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- JSON_THROW_ON_ERROR preserves fail-closed persistence semantics.
		$encoded = json_encode( $appearance->to_array(), JSON_THROW_ON_ERROR );
		$result  = $this->connection->update(
			$this->tables->bots(),
			array( 'appearance_json' => $encoded ),
			array( 'bot_id' => $bot_id->value ),
			array( '%s' ),
			array( '%s' )
		);

		if ( 1 !== $result ) {
			throw new RuntimeException( 'Bot appearance target is missing or could not be updated.' );
		}
	}
}
