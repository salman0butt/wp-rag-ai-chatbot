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
	 * Return valid bindings for the requested bot IDs in one bounded lookup.
	 *
	 * @param array<int,BotId> $bot_ids Stable bot identifiers.
	 * @return array<string,BotRetrievalBinding> Bindings keyed by bot ID.
	 */
	public function find_for_bot_ids( array $bot_ids ): array;

	/**
	 * Determine whether any persisted bot has a complete binding.
	 */
	public function has_any(): bool;

	/**
	 * Persist one trusted binding.
	 *
	 * @param BotId               $bot_id Stable bot identifier.
	 * @param BotRetrievalBinding $binding Trusted retrieval selection.
	 */
	public function save( BotId $bot_id, BotRetrievalBinding $binding ): void;

	/**
	 * Clear only the persisted retrieval binding for one bot.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 */
	public function clear( BotId $bot_id ): void;
}
