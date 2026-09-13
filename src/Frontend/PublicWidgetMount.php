<?php
/**
 * Closed public widget mount boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;
use WpRagAiChatbot\Bots\BotId;

/**
 * Resolves shortcode/mount attributes into the existing public-safe widget projection.
 */
final readonly class PublicWidgetMount {
	/**
	 * Create the mount resolver.
	 *
	 * @param WidgetConfigResolver $configs Existing public-safe widget configuration authority.
	 */
	public function __construct( private WidgetConfigResolver $configs ) {
	}

	/**
	 * Resolve a closed mount attribute set.
	 *
	 * @param array<string,mixed> $attributes Candidate shortcode/mount attributes.
	 */
	public function resolve( array $attributes ): ?WidgetConfig {
		if ( array( 'bot' ) !== array_keys( $attributes ) || ! is_string( $attributes['bot'] ) ) {
			return null;
		}

		try {
			$bot_id = new BotId( trim( $attributes['bot'] ) );
		} catch ( InvalidArgumentException ) {
			return null;
		}

		return $this->configs->resolve( $bot_id );
	}
}
