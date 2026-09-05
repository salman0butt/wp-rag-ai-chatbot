<?php
/**
 * Retrieval configuration bounds.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use InvalidArgumentException;

/**
 * Immutable hard bounds for one retrieval execution.
 */
final readonly class RetrievalConfig {
	/**
	 * Create bounded retrieval configuration.
	 *
	 * @param int $max_query_bytes Maximum normalized query bytes.
	 * @param int $max_query_tokens Maximum lexical query tokens.
	 * @param int $semantic_top_k Maximum semantic matches requested.
	 * @param int $lexical_candidate_limit Maximum lexical candidates scored.
	 * @param int $fused_candidate_limit Maximum fused candidates retained.
	 * @param int $rerank_top_n Maximum candidates sent to a reranker.
	 * @param int $context_candidate_limit Maximum final context candidates.
	 * @throws InvalidArgumentException When any bound is not positive.
	 */
	public function __construct(
		public int $max_query_bytes = 4096,
		public int $max_query_tokens = 128,
		public int $semantic_top_k = 20,
		public int $lexical_candidate_limit = 100,
		public int $fused_candidate_limit = 40,
		public int $rerank_top_n = 20,
		public int $context_candidate_limit = 12
	) {
		if (
			$max_query_bytes < 1 ||
			$max_query_tokens < 1 ||
			$semantic_top_k < 1 ||
			$lexical_candidate_limit < 1 ||
			$fused_candidate_limit < 1 ||
			$rerank_top_n < 1 ||
			$context_candidate_limit < 1
		) {
			throw new InvalidArgumentException( 'Retrieval execution limits must be positive.' );
		}
	}
}
