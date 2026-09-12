<?php
/**
 * Trusted public chat runtime authority.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Bots\Bot;

/**
 * Carries the persisted bot plus its server-owned retrieval binding.
 */
final readonly class PublicChatRuntime {
	/**
	 * Create one trusted public runtime authority.
	 *
	 * @param Bot                 $bot Persisted enabled bot configuration.
	 * @param BotRetrievalBinding $retrieval Persisted retrieval authority.
	 */
	public function __construct(
		public Bot $bot,
		public BotRetrievalBinding $retrieval
	) {
	}
}
