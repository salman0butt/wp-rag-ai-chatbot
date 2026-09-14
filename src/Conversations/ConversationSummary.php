<?php
/**
 * Conversation administration summary projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/**
 * Immutable bounded read model for one conversation list row.
 */
final readonly class ConversationSummary {
	/**
	 * Create one immutable conversation summary.
	 *
	 * @param string      $conversation_id Stable conversation identifier.
	 * @param string|null $bot_id Explicit bot association, or null for historical unassigned rows.
	 * @param string      $started_at Conversation creation timestamp.
	 * @param string|null $latest_message_at Latest canonical message timestamp, or null when empty.
	 * @param int         $message_count Canonical message count.
	 */
	public function __construct(
		public string $conversation_id,
		public ?string $bot_id,
		public string $started_at,
		public ?string $latest_message_at,
		public int $message_count
	) {
	}
}
