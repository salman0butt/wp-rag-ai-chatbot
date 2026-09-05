<?php
/**
 * Bounded deterministic lexical retrieval.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\RetrievalConfig;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Converts bounded lexical projection matches into native ranked candidates.
 */
final class LexicalRetriever {
	/**
	 * Create the lexical retriever.
	 *
	 * @param ChunkSearchStore $store Bounded local lexical projection.
	 * @param LexicalScorer    $scorer Deterministic native scorer.
	 * @param RetrievalConfig  $config Retrieval execution bounds.
	 */
	public function __construct(
		private readonly ChunkSearchStore $store,
		private readonly LexicalScorer $scorer,
		private readonly RetrievalConfig $config
	) {
	}

	/**
	 * Retrieve and rank lexical candidates within trusted scope.
	 *
	 * @param RetrievalQuery $query Preprocessed query.
	 * @param LexicalFilter  $filter Trusted lexical scope.
	 * @return RankedCandidate[]
	 */
	public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array {
		$request = new LexicalSearchRequest(
			$filter,
			$query->lexical_terms,
			$this->config->lexical_candidate_limit
		);

		$candidates = array();
		foreach ( $this->store->search( $request ) as $match ) {
			$record       = $match->record;
			$candidates[] = new RankedCandidate(
				$record->chunk_key,
				$record->document_key,
				$record->source_id,
				$record->content,
				$record->language,
				$record->visibility,
				$this->scorer->score( $query, $record )
			);
		}

		usort(
			$candidates,
			static function ( RankedCandidate $left, RankedCandidate $right ): int {
				$score_order = $right->native_score <=> $left->native_score;
				return 0 !== $score_order ? $score_order : strcmp( $left->chunk_id, $right->chunk_id );
			}
		);

		return array_slice( $candidates, 0, $this->config->fused_candidate_limit );
	}
}
