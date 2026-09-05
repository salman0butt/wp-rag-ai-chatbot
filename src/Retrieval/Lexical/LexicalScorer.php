<?php
/**
 * Deterministic lexical relevance scoring.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Scores bounded lexical candidates with exact-friendly deterministic evidence.
 */
final class LexicalScorer {
	private const EXACT_CONTENT_BOOST = 4.0;
	private const EXACT_TITLE_BOOST   = 2.0;
	private const IDENTIFIER_BOOST    = 2.0;
	private const TERM_BOOST          = 1.0;
	private const TITLE_TERM_BOOST    = 0.25;
	private const MAX_TITLE_BOOST     = 0.5;

	/**
	 * Score one projected chunk against a preprocessed query.
	 *
	 * @param RetrievalQuery   $query Preprocessed retrieval query.
	 * @param ChunkSearchRecord $record Candidate projection record.
	 */
	public function score( RetrievalQuery $query, ChunkSearchRecord $record ): float {
		$content = $this->lowercase( $record->content );
		$title   = $this->lowercase( $record->title );
		$phrase  = $this->lowercase( $query->normalized );
		$score   = 0.0;

		if ( '' !== $phrase && str_contains( $content, $phrase ) ) {
			$score += self::EXACT_CONTENT_BOOST;
		} elseif ( '' !== $phrase && str_contains( $title, $phrase ) ) {
			$score += self::EXACT_TITLE_BOOST;
		}

		$matched_terms = 0;
		$title_boost   = 0.0;
		$terms         = array_values( array_unique( $query->lexical_terms ) );
		foreach ( $terms as $term ) {
			$normalized_term = $this->lowercase( $term );
			$content_match   = str_contains( $content, $normalized_term );
			$title_match     = str_contains( $title, $normalized_term );
			if ( ! $content_match && ! $title_match ) {
				continue;
			}

			++$matched_terms;
			$score += $this->is_identifier( $normalized_term ) ? self::IDENTIFIER_BOOST : self::TERM_BOOST;
			if ( $title_match ) {
				$title_boost += self::TITLE_TERM_BOOST;
			}
		}

		$score += min( self::MAX_TITLE_BOOST, $title_boost );
		if ( array() !== $terms ) {
			$score += $matched_terms / count( $terms );
		}

		return $score;
	}

	/**
	 * Normalize text case without requiring mbstring.
	 *
	 * @param string $value Text to normalize.
	 */
	private function lowercase( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Detect identifier-like query terms that deserve stronger exact evidence.
	 *
	 * @param string $term Normalized lexical term.
	 */
	private function is_identifier( string $term ): bool {
		return 1 === preg_match( '/[0-9_.:\/\-]/', $term );
	}
}
