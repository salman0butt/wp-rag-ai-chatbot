<?php
/**
 * Persisted Playground bot configuration resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use RuntimeException;
use WpRagAiChatbot\Bots\Bot;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/**
 * Resolves one explicitly selected persisted bot without fallback behavior.
 */
final class PlaygroundBotConfigurationResolver {
	/**
	 * Persisted bot repository.
	 *
	 * @var BotRepository
	 */
	private BotRepository $bots;

	/**
	 * Create the resolver.
	 *
	 * @param BotRepository $bots Persisted bot repository.
	 */
	public function __construct( BotRepository $bots ) {
		$this->bots = $bots;
	}

	/**
	 * Resolve one enabled persisted bot by canonical identifier.
	 *
	 * @param string $bot_id Canonical persisted bot identifier.
	 * @throws RuntimeException When the bot does not exist or is disabled.
	 */
	public function resolve( string $bot_id ): Bot {
		$bot = $this->bots->find( new BotId( $bot_id ) );

		if ( null === $bot ) {
			throw new RuntimeException( 'Playground bot configuration was not found.' );
		}

		if ( ! $bot->enabled ) {
			throw new RuntimeException( 'Playground bot configuration is disabled.' );
		}

		return $bot;
	}
}
