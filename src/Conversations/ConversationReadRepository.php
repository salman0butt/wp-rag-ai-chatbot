<?php
/**
 * Conversation administration read repository contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/**
 * Reads bounded canonical conversation administration projections.
 */
interface ConversationReadRepository {
	/**
	 * List conversation summaries in stable recency order.
	 *
	 * @param ConversationListQuery $query Normalized pagination query.
	 * @return list<ConversationSummary>
	 */
	public function list( ConversationListQuery $query ): array;

	/**
	 * Find one conversation with a bounded chronological transcript.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param int    $message_limit Requested transcript message limit.
	 */
	public function find( string $conversation_id, int $message_limit = 100 ): ?ConversationDetail;
}
