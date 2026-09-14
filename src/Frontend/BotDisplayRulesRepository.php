<?php
/**
 * Bot-scoped display-rules persistence contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Bots\BotId;

/** Persists normalized display rules for one stable bot identifier. */
interface BotDisplayRulesRepository {
	/**
	 * Load normalized display rules for one bot.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 */
	public function find( BotId $bot_id ): DisplayRulesConfig;

	/**
	 * Persist normalized display rules for one bot.
	 *
	 * @param BotId              $bot_id Stable bot identifier.
	 * @param DisplayRulesConfig $config Normalized display-rule configuration.
	 */
	public function save( BotId $bot_id, DisplayRulesConfig $config ): void;
}
