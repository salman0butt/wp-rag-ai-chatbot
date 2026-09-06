<?php
/**
 * Message persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Conversations;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Approved M11 domain contract uses explicit owner-scoped snake_case methods.
/**
 * Persists messages only through explicit conversation and owner scope.
 */
interface MessageRepository {
	/** Append one message to an owner-scoped conversation. */
	public function append_for_owner( string $conversation_id, string $owner_scope, ConversationMessage $message ): void;
}
// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
