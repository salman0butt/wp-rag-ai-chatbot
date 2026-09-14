<?php
/**
 * Conversation administration detail projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/** Immutable public-safe administration detail projection. */
final readonly class ConversationDetail {
	/**
	 * Create one administration detail projection.
	 *
	 * @param string      $conversation_id Stable conversation identifier.
	 * @param string|null $bot_id Explicit bot association, or null for historical unassigned rows.
	 * @param string      $started_at Persisted conversation start timestamp.
	 * @param array       $messages Bounded chronological canonical transcript.
	 */
	public function __construct(
		public string $conversation_id,
		public ?string $bot_id,
		public string $started_at,
		public array $messages
	) {
	}
}
