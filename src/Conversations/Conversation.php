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
	 * @param string $conversationId Stable conversation identifier.
	 * @param string $ownerScope Trusted owner scope.
	 */
	public function __construct(
		public readonly string $conversationId,
		public readonly string $ownerScope
	) {
	}
}
