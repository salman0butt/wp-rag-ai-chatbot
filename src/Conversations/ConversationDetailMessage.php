<?php
/**
 * Conversation administration detail message projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

/** Immutable public-safe canonical transcript message projection. */
final readonly class ConversationDetailMessage {
	/**
	 * Create one administration transcript message projection.
	 *
	 * @param string $role Canonical message role.
	 * @param string $content Canonical message content.
	 * @param string $created_at Persisted message timestamp.
	 */
	public function __construct(
		public string $role,
		public string $content,
		public string $created_at
	) {
	}
}
