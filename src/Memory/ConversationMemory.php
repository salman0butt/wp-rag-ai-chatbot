<?php
/**
 * Bounded conversation memory value object.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Memory;

use WpRagAiChatbot\Conversations\ConversationMessage;

/**
 * Immutable memory assembled for one chat request.
 */
final class ConversationMemory {
	/**
	 * Create bounded memory.
	 *
	 * @param list<ConversationMessage> $messages Recent messages in chronological order.
	 * @param int|null                  $summary_version Optional summary version.
	 * @param string|null               $summary_text Optional summary text.
	 */
	public function __construct(
		public readonly array $messages,
		public readonly ?int $summary_version = null,
		public readonly ?string $summary_text = null
	) {
	}
}
