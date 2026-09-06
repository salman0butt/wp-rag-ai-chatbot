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
			'SELECT conversation_id, owner_scope FROM %i WHERE conversation_id = %s AND owner_scope = %s LIMIT 1',
			$this->tables->conversations(),
			$conversation_id,
			$owner_scope
		);
		$row             = $this->connection->get_row( $sql );

		if ( null === $row ) {
			return null;
		}

		return new Conversation( (string) $row['conversation_id'], (string) $row['owner_scope'] );
	}

	/**
	 * Create one owner-scoped conversation.
	 *
	 * @param string $owner_scope Trusted owner scope.
	 * @throws InvalidArgumentException When owner scope is invalid.
	 * @throws DatabaseException When persistence fails.
	 */
	public function create_for_owner( string $owner_scope ): Conversation {
		$owner_scope     = $this->boundedOwnerScope( $owner_scope );
		$conversation_id = bin2hex( random_bytes( 16 ) );
		$now             = gmdate( 'Y-m-d H:i:s' );
		$result          = $this->connection->insert(
			$this->tables->conversations(),
			array(
				'conversation_id' => $conversation_id,
				'owner_scope'     => $owner_scope,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			throw new DatabaseException( 'Could not create conversation.' );
		}

		return new Conversation( $conversation_id, $owner_scope );
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
}
// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
