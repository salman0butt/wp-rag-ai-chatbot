<?php
/**
 * Resolved persisted Playground configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Bots\Bot;

/**
 * Carries the closed persisted configuration required for one Playground composition.
 */
final readonly class PlaygroundConfiguration {
	/**
	 * Create one resolved Playground configuration.
	 *
	 * @param Bot                              $bot Enabled persisted bot configuration.
	 * @param PlaygroundRetrievalConfiguration $retrieval Explicit persisted source and collection selection.
	 */
	public function __construct(
		public Bot $bot,
		public PlaygroundRetrievalConfiguration $retrieval
	) {
	}
}
