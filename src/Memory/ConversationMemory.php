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
	 * Recent messages in chronological order.
	 *
	 * @var list<ConversationMessage>
	 */
	public readonly array $messages;

	/**
	 * Optional summary version.
	 */
	public readonly ?int $summary_version;

	/**
	 * Optional summary text.
	 */
	public readonly ?string $summary_text;

	/**
	 * Create bounded memory.
	 *
	 * @param array       $messages Recent messages in chronological order.
	 * @param int|null    $summary_version Optional summary version.
	 * @param string|null $summary_text Optional summary text.
	 * @phpstan-param list<ConversationMessage> $messages
	 */
	public function __construct( array $messages, ?int $summary_version = null, ?string $summary_text = null ) {
		$this->messages        = $messages;
		$this->summary_version = $summary_version;
		$this->summary_text    = $summary_text;
	}
}
