<?php
/**
 * Bot-scoped appearance persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Bots\BotId;

/** Persists normalized chatbot appearance for one stable bot identifier. */
interface BotAppearanceRepository {
	/**
	 * Load normalized appearance for one bot.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 */
	public function find( BotId $bot_id ): AppearanceConfig;

	/**
	 * Persist normalized appearance for one bot.
	 *
	 * @param BotId           $bot_id Stable bot identifier.
	 * @param AppearanceConfig $appearance Normalized appearance.
	 */
	public function save( BotId $bot_id, AppearanceConfig $appearance ): void;
}
