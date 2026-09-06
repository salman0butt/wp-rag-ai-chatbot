<?php
/**
 * Optional reranker contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Rerank;

/**
 * Reranks a bounded access-approved candidate request.
 */
interface Reranker {
	/**
	 * Rerank supplied candidates without changing their trusted lineage.
	 *
	 * @param RerankRequest $request Bounded access-approved request.
	 */
	public function rerank( RerankRequest $request ): RerankResult;
}
