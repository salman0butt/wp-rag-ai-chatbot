<?php
/**
 * Conversation message record.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/**
 * Immutable message payload for scoped persistence.
 */
final class ConversationMessage {
	/**
	 * @param string $role Message role.
	 * @param string $content Message content.
	 */
	public function __construct(
		public readonly string $role,
		public readonly string $content
	) {
	}
}
