<?php
/**
 * WordPress conversation repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use InvalidArgumentException;
use WpRagAiChatbot\Conversations\Conversation;
use WpRagAiChatbot\Conversations\ConversationRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\TableNames;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Approved M11 repository contract uses explicit owner-scoped snake_case methods.
/**
 * Persists conversations with mandatory owner scope.
 */
final class WpdbConversationRepository implements ConversationRepository {
	private const MAX_IDENTIFIER_BYTES = 191;
	private const MAX_BOT_ID_BYTES     = 32;

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
	 * Find a conversation only when both stable identifier and owner scope match.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 * @throws InvalidArgumentException When either persisted identifier is invalid.
	 */
	public function find_for_owner( string $conversation_id, string $owner_scope ): ?Conversation {
		$conversation_id = $this->boundedConversationId( $conversation_id );
		$owner_scope     = $this->boundedOwnerScope( $owner_scope );
		$sql             = $this->connection->prepare(
			'SELECT conversation_id, owner_scope, bot_id FROM %i WHERE conversation_id = %s AND owner_scope = %s LIMIT 1',
			$this->tables->conversations(),
			$conversation_id,
			$owner_scope
		);
		$row             = $this->connection->get_row( $sql );

		if ( null === $row ) {
			return null;
		}

		$bot_id = null;
		if ( array_key_exists( 'bot_id', $row ) && null !== $row['bot_id'] ) {
			$bot_id = (string) $row['bot_id'];
		}

		return new Conversation(
			(string) $row['conversation_id'],
			(string) $row['owner_scope'],
			$bot_id
		);
	}

	/**
	 * Create one owner-scoped conversation with an optional explicit bot association.
	 *
	 * The optional association keeps historical and non-bot creation callers backward compatible while
	 * allowing M16 public creation paths to persist bot identity independently of owner scope.
	 *
	 * @param string      $owner_scope Trusted owner scope.
	 * @param string|null $bot_id Explicit persisted bot identifier when the creating path has bot authority.
	 * @throws DatabaseException When persistence fails.
	 */
	public function create_for_owner( string $owner_scope, ?string $bot_id = null ): Conversation {
		$owner_scope     = $this->boundedOwnerScope( $owner_scope );
		$conversation_id = bin2hex( random_bytes( 16 ) );
		$now             = gmdate( 'Y-m-d H:i:s' );
		$data            = array(
			'conversation_id' => $conversation_id,
			'owner_scope'     => $owner_scope,
			'created_at'      => $now,
			'updated_at'      => $now,
		);
		$formats         = array( '%s', '%s', '%s', '%s' );

		if ( null !== $bot_id ) {
			$bot_id        = $this->boundedBotId( $bot_id );
			$data['bot_id'] = $bot_id;
			$formats[]      = '%s';
		}

		$result = $this->connection->insert(
			$this->tables->conversations(),
			$data,
			$formats
		);

		if ( false === $result ) {
			throw new DatabaseException( 'Could not create conversation.' );
		}

		return new Conversation( $conversation_id, $owner_scope, $bot_id );
	}

	/**
	 * Normalize and hard-bound a conversation identifier.
	 *
	 * @param string $value Raw identifier.
	 * @throws InvalidArgumentException When the identifier is blank or oversized.
	 */
	private function boundedConversationId( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			throw new InvalidArgumentException( 'Conversation identifier must not be blank.' );
		}
		if ( strlen( $value ) > self::MAX_IDENTIFIER_BYTES ) {
			throw new InvalidArgumentException( 'Conversation identifier exceeds the persistence limit.' );
		}
		return $value;
	}

	/**
	 * Normalize and hard-bound trusted owner scope.
	 *
	 * @param string $value Raw owner scope.
	 * @throws InvalidArgumentException When owner scope is blank or oversized.
	 */
	private function boundedOwnerScope( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			throw new InvalidArgumentException( 'Owner scope must not be blank.' );
		}
		if ( strlen( $value ) > self::MAX_IDENTIFIER_BYTES ) {
			throw new InvalidArgumentException( 'Owner scope exceeds the persistence limit.' );
		}
		return $value;
	}

	/**
	 * Normalize and hard-bound an explicit bot association.
	 *
	 * @param string $value Raw bot identifier.
	 * @throws InvalidArgumentException When bot identity is blank or oversized.
	 */
	private function boundedBotId( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			throw new InvalidArgumentException( 'Bot identifier must not be blank.' );
		}
		if ( strlen( $value ) > self::MAX_BOT_ID_BYTES ) {
			throw new InvalidArgumentException( 'Bot identifier exceeds the persistence limit.' );
		}
		return $value;
	}
}
// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
