<?php
/**
 * Request-local retrieval capture for administrator Playground execution.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Chat\ChatRetrievalObserver;
use WpRagAiChatbot\Retrieval\RetrievalResult;

/**
 * Captures the exact retrieval result already consumed by chat orchestration.
 */
final class PlaygroundRetrievalCapture implements ChatRetrievalObserver {
	/**
	 * Exact observed production retrieval result.
	 */
	private ?RetrievalResult $result = null;

	/**
	 * Capture the exact retrieval result consumed by the request-local orchestrator.
	 *
	 * @param RetrievalResult $result Exact production retrieval result.
	 */
	public function observe( RetrievalResult $result ): void {
		$this->result = $result;
	}

	/**
	 * Return the observed result when retrieval has completed.
	 */
	public function result(): ?RetrievalResult {
		return $this->result;
	}
}
