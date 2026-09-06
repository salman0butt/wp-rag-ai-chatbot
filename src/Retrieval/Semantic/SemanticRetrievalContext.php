<?php
/**
 * M10 semantic retrieval context.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Semantic;

use Closure;
use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;

/**
 * Carries trusted policy and a bounded canonical-content resolver for semantic matches.
 */
final readonly class SemanticRetrievalContext {
	/**
	 * Create one semantic retrieval context.
	 *
	 * @param RetrievalFilter $filter Trusted server-side retrieval constraints.
	 * @param Closure         $chunk_resolver Bounded canonical chunk resolver.
	 * @phpstan-param Closure(string): ?ChunkSearchRecord $chunk_resolver
	 */
	public function __construct(
		public RetrievalFilter $filter,
		private Closure $chunk_resolver
	) {
	}

	/**
	 * Resolve one matched vector ID to its canonical local search projection.
	 *
	 * @param string $chunk_id Stable vector/chunk identifier.
	 */
	public function resolve_chunk( string $chunk_id ): ?ChunkSearchRecord {
		return ( $this->chunk_resolver )( $chunk_id );
	}
}
