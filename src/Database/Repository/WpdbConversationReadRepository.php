<?php
/**
 * WordPress database conversation administration reads.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use WpRagAiChatbot\Conversations\ConversationListQuery;
use WpRagAiChatbot\Conversations\ConversationReadRepository;
use WpRagAiChatbot\Conversations\ConversationSummary;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;

/**
 * Reads bounded conversation administration projections from canonical tables.
 */
final class WpdbConversationReadRepository implements ConversationReadRepository {
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
		$sql    = $this->connection->prepare(
			'SELECT c.conversation_id, c.bot_id, c.created_at AS started_at, MAX(m.created_at) AS latest_message_at, COUNT(m.id) AS message_count
			FROM %i AS c
			LEFT JOIN %i AS m ON m.conversation_id = c.conversation_id AND m.owner_scope = c.owner_scope
			GROUP BY c.id, c.conversation_id, c.bot_id, c.created_at
			ORDER BY COALESCE(MAX(m.created_at), c.created_at) DESC, c.id DESC
			LIMIT %d OFFSET %d',
			$this->tables->conversations(),
			$this->tables->messages(),
			$query->page_size,
			$offset
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
}
