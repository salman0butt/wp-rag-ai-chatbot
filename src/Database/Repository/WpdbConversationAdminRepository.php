<?php
/**
 * WordPress database conversation administration mutations.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use InvalidArgumentException;
use Throwable;
use WpRagAiChatbot\Conversations\ConversationAdminRepository;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Database\TableNames;

/** Explicit administrator mutations over canonical conversation tables. */
final class WpdbConversationAdminRepository implements ConversationAdminRepository {
	private const MAX_IDENTIFIER_BYTES = 191;

	/**
	 * Create the administrator mutation repository.
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
	 * Delete one canonical conversation and dependent canonical messages atomically.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @throws InvalidArgumentException When the identifier is blank or oversized.
	 * @throws DatabaseException When a persistence or transaction operation fails.
	 */
	public function delete( string $conversation_id ): bool {
		$conversation_id = $this->boundedConversationId( $conversation_id );

		if ( false === $this->connection->query( 'START TRANSACTION' ) ) {
			throw new DatabaseException( 'Could not start conversation deletion transaction.' );
		}

		try {
			$sql = $this->connection->prepare(
				'SELECT owner_scope FROM %i WHERE conversation_id = %s LIMIT 1 FOR UPDATE',
				$this->tables->conversations(),
				$conversation_id
			);
			$row = $this->connection->get_row( $sql );

			if ( null === $row ) {
				$this->commit();
				return false;
			}

			$owner_scope = isset( $row['owner_scope'] ) && is_string( $row['owner_scope'] )
				? trim( $row['owner_scope'] )
				: '';
			if ( '' === $owner_scope ) {
				throw new DatabaseException( 'Conversation owner scope is missing.' );
			}

			$message_delete = $this->connection->delete(
				$this->tables->messages(),
				array(
					'conversation_id' => $conversation_id,
					'owner_scope'     => $owner_scope,
				),
				array( '%s', '%s' )
			);
			if ( false === $message_delete ) {
				throw new DatabaseException( 'Could not delete dependent conversation messages.' );
			}

			$conversation_delete = $this->connection->delete(
				$this->tables->conversations(),
				array(
					'conversation_id' => $conversation_id,
					'owner_scope'     => $owner_scope,
				),
				array( '%s', '%s' )
			);
			if ( false === $conversation_delete ) {
				throw new DatabaseException( 'Could not delete conversation.' );
			}
			if ( 0 === $conversation_delete ) {
				throw new DatabaseException( 'Conversation disappeared during deletion.' );
			}

			$this->commit();
			return true;
		} catch ( Throwable ) {
			$this->connection->query( 'ROLLBACK' );
			throw new DatabaseException( 'Conversation deletion failed.' );
		}
	}

	/**
	 * Commit the active deletion transaction.
	 *
	 * @throws DatabaseException When the transaction cannot be committed.
	 */
	private function commit(): void {
		if ( false === $this->connection->query( 'COMMIT' ) ) {
			throw new DatabaseException( 'Could not commit conversation deletion transaction.' );
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
}
