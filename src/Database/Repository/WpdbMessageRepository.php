<?php
/**
 * WordPress conversation message repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use InvalidArgumentException;
use WpRagAiChatbot\Conversations\ConversationMessage;
use WpRagAiChatbot\Conversations\MessageRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\TableNames;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Approved M11 repository contract uses explicit owner-scoped snake_case methods.
/**
 * Persists messages through one owner-scoped atomic insert-select.
 */
final class WpdbMessageRepository implements MessageRepository {
	private const MAX_IDENTIFIER_BYTES = 191;
	private const MAX_ROLE_BYTES       = 32;
	private const MAX_CONTENT_BYTES    = 65536;

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
	 * Append a message only when the supplied owner owns the conversation.
	 *
	 * @param string              $conversation_id Stable conversation identifier.
	 * @param string              $owner_scope Trusted owner scope.
	 * @param ConversationMessage $message Message to append.
	 * @throws InvalidArgumentException When a persisted field is invalid.
	 * @throws DatabaseException When the append fails or owner scope does not match.
	 */
	public function append_for_owner( string $conversation_id, string $owner_scope, ConversationMessage $message ): void {
		$conversation_id = $this->boundedConversationId( $conversation_id );
		$owner_scope     = $this->boundedOwnerScope( $owner_scope );
		$role            = strtolower( trim( $message->role ) );
		$content         = $message->content;

		if ( '' === $role ) {
			throw new InvalidArgumentException( 'Message role must not be blank.' );
		}
		if ( strlen( $role ) > self::MAX_ROLE_BYTES ) {
			throw new InvalidArgumentException( 'Message role exceeds the persistence limit.' );
		}
		if ( '' === $content ) {
			throw new InvalidArgumentException( 'Message content must not be blank.' );
		}
		if ( strlen( $content ) > self::MAX_CONTENT_BYTES ) {
			throw new InvalidArgumentException( 'Message content exceeds the persistence limit.' );
		}

		$sql    = $this->connection->prepare(
			'INSERT INTO %i (conversation_id, owner_scope, role, content, metadata_json, created_at) SELECT %s, %s, %s, %s, NULL, UTC_TIMESTAMP() FROM %i WHERE conversation_id = %s AND owner_scope = %s LIMIT 1',
			$this->tables->messages(),
			$conversation_id,
			$owner_scope,
			$role,
			$content,
			$this->tables->conversations(),
			$conversation_id,
			$owner_scope
		);
		$result = $this->connection->query( $sql );

		if ( false === $result ) {
			throw new DatabaseException( 'Could not append conversation message.' );
		}
		if ( 1 !== $result ) {
			throw new DatabaseException( 'Conversation is unavailable for message append.' );
		}
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
