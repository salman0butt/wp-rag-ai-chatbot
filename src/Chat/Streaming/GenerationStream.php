<?php
/**
 * Provider-neutral generation stream boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat\Streaming;

/**
 * Hides vendor stream event names and payloads behind normalized text deltas.
 */
interface GenerationStream {
	/**
	 * Read the next normalized text delta, or null when the stream is complete.
	 */
	public function next_delta(): ?string;

	/**
	 * Stop consuming provider resources and release stream state.
	 */
	public function close(): void;
}
