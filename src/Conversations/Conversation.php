<?php
/**
 * Conversation record.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/**
 * Immutable owner-scoped conversation identity.
 */
final class Conversation {
	/**
	 * Create a conversation identity.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 */
	public function __construct(
		public readonly string $conversation_id,
		public readonly string $owner_scope
	) {
	}
}
