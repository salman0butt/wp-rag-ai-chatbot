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
		foreach (
			array(
				'max_query_bytes'           => $max_query_bytes,
				'max_query_tokens'          => $max_query_tokens,
				'semantic_top_k'            => $semantic_top_k,
				'lexical_candidate_limit'   => $lexical_candidate_limit,
				'fused_candidate_limit'     => $fused_candidate_limit,
				'rerank_top_n'              => $rerank_top_n,
				'context_candidate_limit'   => $context_candidate_limit,
			) as $name => $value
		) {
			if ( $value < 1 ) {
				throw new InvalidArgumentException( $name . ' must be positive.' );
			}
		}
	}
}
