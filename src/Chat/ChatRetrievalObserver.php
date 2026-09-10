<?php
/**
 * Request-local production retrieval observation seam.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use WpRagAiChatbot\Retrieval\RetrievalResult;

/**
 * Observes the exact retrieval result already consumed by chat orchestration.
 */
interface ChatRetrievalObserver {
	/**
	 * Observe one completed production retrieval result.
	 *
	 * @param RetrievalResult $result Exact production retrieval result.
	 */
	public function observe( RetrievalResult $result ): void;
}
