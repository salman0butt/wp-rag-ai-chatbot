<?php
/**
 * Bot-scoped public retrieval binding persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Bots\BotId;

/**
 * Persists the trusted knowledge source and vector collection selected for one bot.
 */
interface BotRetrievalBindingRepository {
	/**
	 * Load one persisted binding.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 */
	public function find( BotId $bot_id ): ?BotRetrievalBinding;

	/**
	 * Persist one trusted binding.
	 *
	 * @param BotId               $bot_id Stable bot identifier.
	 * @param BotRetrievalBinding $binding Trusted retrieval selection.
	 */
	public function save( BotId $bot_id, BotRetrievalBinding $binding ): void;
}
