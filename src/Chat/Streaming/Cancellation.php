<?php
/**
 * Application-owned streaming cancellation state.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat\Streaming;

/**
 * Mutable, idempotent cancellation flag checked between stream reads.
 */
final class Cancellation {
	private bool $cancelled = false;

	/**
	 * Request cancellation.
	 */
	public function cancel(): void {
		$this->cancelled = true;
	}

	/**
	 * Whether cancellation has been requested.
	 */
	public function is_cancelled(): bool {
		return $this->cancelled;
	}
}
