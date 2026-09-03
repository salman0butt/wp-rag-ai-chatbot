<?php
/**
 * Token counting contract for deterministic chunk budgets.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Chunking;

/**
 * Counts deterministic lexical units for chunk-budget decisions.
 */
interface TokenCounter {
	/**
	 * Count token-budget units in text.
	 *
	 * @param string $text Text to count.
	 */
	public function count( string $text ): int;
}
