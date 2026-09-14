<?php
/**
 * Conversation persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Approved M11 domain contract uses explicit owner-scoped snake_case methods.
/**
 * Persists conversations only through explicit owner scope.
 */
interface ConversationRepository {
	/**
	 * Find one conversation belonging to the supplied owner scope.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 */
	public function find_for_owner( string $conversation_id, string $owner_scope ): ?Conversation;

	/**
	 * Create one conversation belonging to the supplied owner scope.
	 *
	 * @param string      $owner_scope Trusted owner scope.
	 * @param string|null $bot_id Explicit bot association, or null for non-bot/historical-compatible callers.
	 */
	public function create_for_owner( string $owner_scope, ?string $bot_id = null ): Conversation;
}
// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
