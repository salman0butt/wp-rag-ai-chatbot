<?php
/**
 * Public-safe widget configuration resolver.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Bots\BotId;
use WpRagAiChatbot\Bots\BotRepository;

/** Resolves the explicit public projection for one enabled bot. */
final readonly class WidgetConfigResolver {
	/**
	 * Create the resolver.
	 *
	 * @param BotRepository           $bots Persisted bot authority.
	 * @param BotAppearanceRepository $appearances Persisted appearance authority.
	 */
	public function __construct(
		private BotRepository $bots,
		private BotAppearanceRepository $appearances
	) {
	}

	/**
	 * Resolve one public-safe widget configuration.
	 *
	 * @param BotId $bot_id Stable bot identifier.
	 */
	public function resolve( BotId $bot_id ): ?WidgetConfig {
		$bot = $this->bots->find( $bot_id );
		if ( null === $bot || ! $bot->enabled ) {
			return null;
		}

		return new WidgetConfig(
			$bot->id->value,
			$bot->name,
			$this->appearances->find( $bot_id )
		);
	}
}
