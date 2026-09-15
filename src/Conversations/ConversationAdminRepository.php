<?php
/**
 * Conversation administration mutation repository contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/** Explicit administrator mutation boundary for canonical conversations. */
interface ConversationAdminRepository {
	/**
	 * Delete one canonical conversation and its dependent rows.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @return bool True when a conversation was deleted; false when it was missing.
	 */
	public function delete( string $conversation_id ): bool;
}
