<?php
/**
 * No-op reranker.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Rerank;

/**
 * Preserves fused ordering and scores when no relevance reranking is desired.
 */
final class NoOpReranker implements Reranker {
	/**
	 * Return supplied candidates in their existing order.
	 *
	 * @param RerankRequest $request Bounded access-approved request.
	 */
	public function rerank( RerankRequest $request ): RerankResult {
		$scores = array();
		foreach ( $request->candidates as $candidate ) {
			$scores[ $candidate->chunk_id ] = $candidate->fused_score;
		}

		return new RerankResult( $scores );
	}
}
