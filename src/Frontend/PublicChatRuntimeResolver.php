<?php
/**
 * Public chat persisted runtime resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use RuntimeException;
use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/**
 * Resolves the enabled persisted bot and its trusted retrieval authority.
 */
final readonly class PublicChatRuntimeResolver {
	/**
	 * Create one runtime resolver.
	 *
	 * @param BotRepository                 $bots Persisted bot authority.
	 * @param BotRetrievalBindingRepository $bindings Persisted retrieval authority.
	 */
	public function __construct(
		private BotRepository $bots,
		private BotRetrievalBindingRepository $bindings
	) {
	}

	/**
	 * Resolve one enabled persisted public runtime.
	 *
	 * @param string $bot_id Stable bot identifier from the bounded public request.
	 * @throws RuntimeException When the bot or its trusted retrieval binding is unavailable.
	 */
	public function resolve( string $bot_id ): PublicChatRuntime {
		$id  = new BotId( $bot_id );
		$bot = $this->bots->find( $id );

		if ( null === $bot || ! $bot->enabled ) {
			throw new RuntimeException( 'Public chat runtime is unavailable.' );
		}

		$retrieval = $this->bindings->find( $id );
		if ( null === $retrieval ) {
			throw new RuntimeException( 'Public chat runtime is unavailable.' );
		}

		return new PublicChatRuntime( $bot, $retrieval );
	}
}
