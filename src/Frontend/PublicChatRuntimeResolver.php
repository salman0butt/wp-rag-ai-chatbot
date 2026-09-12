<?php
/**
 * Public chat persisted runtime resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use RuntimeException;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/**
 * Resolves the enabled persisted bot that owns public chat runtime authority.
 */
final readonly class PublicChatRuntimeResolver {
	/**
	 * Create one runtime resolver.
	 *
	 * @param BotRepository $bots Persisted bot authority.
	 */
	public function __construct( private BotRepository $bots ) {
	}

	/**
	 * Resolve one enabled persisted bot.
	 *
	 * @param string $bot_id Stable bot identifier from the bounded public request.
	 * @throws RuntimeException When the bot is missing or disabled.
	 */
	public function resolve( string $bot_id ): Bot {
		$bot = $this->bots->find( new BotId( $bot_id ) );

		if ( null === $bot || ! $bot->enabled ) {
			throw new RuntimeException( 'Public chat runtime is unavailable.' );
		}

		return $bot;
	}
}
