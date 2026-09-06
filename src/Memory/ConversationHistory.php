<?php
/**
 * Owner-scoped conversation history contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Memory;

use WpRagAiChatbot\Conversations\ConversationMessage;

/**
 * Supplies bounded conversation history using trusted owner scope.
 */
interface ConversationHistory {
	/**
	 * Return recent messages for one owner-scoped conversation.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 * @param int    $limit Hard maximum number of recent messages requested.
	 * @return list<ConversationMessage>
	 */
	public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array;

	/**
	 * Return at most one versioned summary for one owner-scoped conversation.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 * @return array{version:int,text:string}|null
	 */
	public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array;
}
