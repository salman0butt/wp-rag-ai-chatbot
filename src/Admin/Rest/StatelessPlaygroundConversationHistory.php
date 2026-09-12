<?php
/**
 * Fail-closed stateless Playground conversation-history sentinel.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use LogicException;
use WpRagAiChatbot\Memory\ConversationHistory;

/**
 * Prevents Task 6 from silently fabricating unfinished stateful M11 memory.
 *
 * Production Playground requests do not carry a conversation ID, so the M11
 * orchestrator never reads this dependency. Any accidental future stateful use
 * fails closed instead of returning misleading empty history.
 */
final class StatelessPlaygroundConversationHistory implements ConversationHistory {
	/**
	 * Reject accidental stateful message reads.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 * @param int    $limit Requested message limit.
	 * @return list<\WpRagAiChatbot\Conversations\ConversationMessage>
	 * @throws LogicException Always; Playground execution is stateless.
	 */
	public function recent_for_owner( string $conversation_id, string $owner_scope, int $limit ): array {
		unset( $conversation_id, $owner_scope, $limit );

		throw new LogicException( 'Playground conversation history is unavailable for stateless execution.' );
	}

	/**
	 * Reject accidental stateful summary reads.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 * @return array{version:int,text:string}|null
	 * @throws LogicException Always; Playground execution is stateless.
	 */
	public function summary_for_owner( string $conversation_id, string $owner_scope ): ?array {
		unset( $conversation_id, $owner_scope );

		throw new LogicException( 'Playground conversation summaries are unavailable for stateless execution.' );
	}
}
