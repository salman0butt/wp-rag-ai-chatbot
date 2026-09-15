<?php
/**
 * WordPress database conversation administration reads.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use WpRagAiChatbot\Conversations\ConversationDetail;
use WpRagAiChatbot\Conversations\ConversationDetailMessage;
use WpRagAiChatbot\Conversations\ConversationListQuery;
use WpRagAiChatbot\Conversations\ConversationReadRepository;
use WpRagAiChatbot\Conversations\ConversationSummary;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;

/**
 * Reads bounded conversation administration projections from canonical tables.
 */
final class WpdbConversationReadRepository implements ConversationReadRepository {
	private const MAX_IDENTIFIER_BYTES = 191;
	private const MAX_DETAIL_MESSAGES  = 100;

	/**
	 * Create the read repository.
	 *
	 * @param Connection $connection Database connection.
	 * @param TableNames $tables Table-name resolver.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * List conversation summaries in stable recency order.
	 *
	 * @param ConversationListQuery $query Normalized pagination query.
	 * @return list<ConversationSummary>
	 */
	public function list( ConversationListQuery $query ): array {
		$offset = ( $query->page - 1 ) * $query->page_size;

		/**
		 * Controlled SQL filter suffix.
		 *
		 * @var literal-string $where
		 */
		$where = '';
		$args  = array(
			$this->tables->conversations(),
			$this->tables->messages(),
		);

		if ( null !== $query->bot_id ) {
			$where  = 'WHERE c.bot_id = %s';
			$args[] = $query->bot_id;
		} elseif ( $query->unassigned_only ) {
			$where = 'WHERE c.bot_id IS NULL';
		}

		if ( null !== $query->date_from ) {
			$where .= '' === $where ? 'WHERE c.created_at >= %s' : ' AND c.created_at >= %s';
			$args[] = $query->date_from;
		}

		if ( null !== $query->date_to ) {
			$where .= '' === $where ? 'WHERE c.created_at <= %s' : ' AND c.created_at <= %s';
			$args[] = $query->date_to;
		}

		if ( null !== $query->search ) {
			$where .= '' === $where
				? 'WHERE EXISTS (SELECT 1 FROM %i AS sm WHERE sm.conversation_id = c.conversation_id AND sm.owner_scope = c.owner_scope AND sm.content LIKE %s)'
				: ' AND EXISTS (SELECT 1 FROM %i AS sm WHERE sm.conversation_id = c.conversation_id AND sm.owner_scope = c.owner_scope AND sm.content LIKE %s)';
			$args[] = $this->tables->messages();
			$args[] = '%' . addcslashes( $query->search, '\\%_' ) . '%';
		}

		$args[] = $query->page_size;
		$args[] = $offset;

		$sql = $this->connection->prepare(
			'SELECT c.conversation_id, c.bot_id, c.created_at AS started_at, MAX(m.created_at) AS latest_message_at, COUNT(m.id) AS message_count
			FROM %i AS c
			LEFT JOIN %i AS m ON m.conversation_id = c.conversation_id AND m.owner_scope = c.owner_scope
			' . $where . '
			GROUP BY c.id, c.conversation_id, c.bot_id, c.created_at
			ORDER BY COALESCE(MAX(m.created_at), c.created_at) DESC, c.id DESC
			LIMIT %d OFFSET %d',
			...$args
		);

		$summaries = array();
		foreach ( $this->connection->get_results( $sql ) as $row ) {
			$conversation_id = isset( $row['conversation_id'] ) && is_string( $row['conversation_id'] )
				? trim( $row['conversation_id'] )
				: '';
			$started_at      = isset( $row['started_at'] ) && is_string( $row['started_at'] )
				? trim( $row['started_at'] )
				: '';
			if ( '' === $conversation_id || '' === $started_at ) {
				continue;
			}

			$bot_id = isset( $row['bot_id'] ) && is_string( $row['bot_id'] ) ? trim( $row['bot_id'] ) : null;
			if ( '' === $bot_id ) {
				$bot_id = null;
			}

			$latest_message_at = isset( $row['latest_message_at'] ) && is_string( $row['latest_message_at'] )
				? trim( $row['latest_message_at'] )
				: null;
			if ( '' === $latest_message_at ) {
				$latest_message_at = null;
			}

			$message_count = isset( $row['message_count'] ) && ( is_int( $row['message_count'] ) || is_numeric( $row['message_count'] ) )
				? max( 0, (int) $row['message_count'] )
				: 0;

			$summaries[] = new ConversationSummary(
				$conversation_id,
				$bot_id,
				$started_at,
				$latest_message_at,
				$message_count
			);
		}

		return $summaries;
	}

	/**
	 * Find one conversation with a bounded chronological transcript.
	 *
	 * Owner scope is used only to constrain the canonical message query and is
	 * never projected into the administration DTO.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param int    $message_limit Requested transcript message limit.
	 */
	public function find( string $conversation_id, int $message_limit = self::MAX_DETAIL_MESSAGES ): ?ConversationDetail {
		$conversation_id = trim( $conversation_id );
		if ( '' === $conversation_id || strlen( $conversation_id ) > self::MAX_IDENTIFIER_BYTES ) {
			return null;
		}

		$sql = $this->connection->prepare(
			'SELECT c.conversation_id, c.bot_id, c.owner_scope, c.created_at AS started_at
			FROM %i AS c
			WHERE c.conversation_id = %s
			LIMIT 1',
			$this->tables->conversations(),
			$conversation_id
		);
		$row = $this->connection->get_row( $sql );
		if ( null === $row ) {
			return null;
		}

		$persisted_id = isset( $row['conversation_id'] ) && is_string( $row['conversation_id'] )
			? trim( $row['conversation_id'] )
			: '';
		$owner_scope  = isset( $row['owner_scope'] ) && is_string( $row['owner_scope'] )
			? trim( $row['owner_scope'] )
			: '';
		$started_at   = isset( $row['started_at'] ) && is_string( $row['started_at'] )
			? trim( $row['started_at'] )
			: '';
		if ( '' === $persisted_id || '' === $owner_scope || '' === $started_at ) {
			return null;
		}

		$bot_id = isset( $row['bot_id'] ) && is_string( $row['bot_id'] ) ? trim( $row['bot_id'] ) : null;
		if ( '' === $bot_id ) {
			$bot_id = null;
		}

		$message_limit = min( max( 0, $message_limit ), self::MAX_DETAIL_MESSAGES );
		$messages      = array();
		if ( 0 < $message_limit ) {
			$message_sql = $this->connection->prepare(
				'SELECT m.role, m.content, m.created_at
				FROM %i AS m
				WHERE m.conversation_id = %s AND m.owner_scope = %s
				ORDER BY m.created_at ASC, m.id ASC
				LIMIT %d',
				$this->tables->messages(),
				$persisted_id,
				$owner_scope,
				$message_limit
			);

			foreach ( $this->connection->get_results( $message_sql ) as $message_row ) {
				$role       = isset( $message_row['role'] ) && is_string( $message_row['role'] )
					? trim( $message_row['role'] )
					: '';
				$content    = isset( $message_row['content'] ) && is_string( $message_row['content'] )
					? $message_row['content']
					: '';
				$created_at = isset( $message_row['created_at'] ) && is_string( $message_row['created_at'] )
					? trim( $message_row['created_at'] )
					: '';
				if ( '' === $role || '' === $content || '' === $created_at ) {
					continue;
				}

				$messages[] = new ConversationDetailMessage( $role, $content, $created_at );
			}
		}

		return new ConversationDetail( $persisted_id, $bot_id, $started_at, $messages );
	}
}
