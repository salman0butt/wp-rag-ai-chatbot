<?php
/**
 * Deterministic local lexical-overlap reranker.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Rerank;

/**
 * Scores access-approved candidates by deterministic query-term coverage.
 */
final class LexicalOverlapReranker implements Reranker {
	/**
	 * Rerank supplied candidates by bounded lexical overlap.
	 *
	 * @param RerankRequest $request Bounded access-approved request.
	 */
	public function rerank( RerankRequest $request ): RerankResult {
		$rows       = array();
		$term_count = count( $request->query->lexical_terms );

		foreach ( $request->candidates as $index => $candidate ) {
			$content = function_exists( 'mb_strtolower' )
				? mb_strtolower( $candidate->content, 'UTF-8' )
				: strtolower( $candidate->content );
			$matches = 0;

			foreach ( $request->query->lexical_terms as $term ) {
				$normalized_term = function_exists( 'mb_strtolower' )
					? mb_strtolower( $term, 'UTF-8' )
					: strtolower( $term );
				if ( '' !== $normalized_term && str_contains( $content, $normalized_term ) ) {
					++$matches;
				}
			}

			$rows[] = array(
				'id'    => $candidate->chunk_id,
				'score' => 0 === $term_count ? 0.0 : $matches / $term_count,
				'index' => $index,
			);
		}

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				$score_order = $right['score'] <=> $left['score'];
				return 0 !== $score_order ? $score_order : $left['index'] <=> $right['index'];
			}
		);

		$scores = array();
		foreach ( $rows as $row ) {
			$scores[ $row['id'] ] = $row['score'];
		}

		return new RerankResult( $scores );
	}
}
