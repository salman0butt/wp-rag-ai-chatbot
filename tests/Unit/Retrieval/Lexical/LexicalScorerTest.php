<?php
/**
 * Deterministic M10 lexical scoring tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Lexical;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Lexical\ChunkSearchRecord;
use WpRagAiChatbot\Retrieval\Lexical\LexicalScorer;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Defines exact-friendly deterministic lexical ranking semantics.
 */
final class LexicalScorerTest extends TestCase {
	/**
	 * An exact normalized full-query occurrence outranks generic term overlap.
	 */
	public function test_exact_full_query_outranks_generic_overlap(): void {
		$scorer = new LexicalScorer();
		$query  = new RetrievalQuery( 'install sku-42/a', array( 'install', 'sku-42/a' ) );

		$exact   = $this->record( 'a', 'Install SKU-42/A before powering the unit.' );
		$generic = $this->record( 'b', 'Install the unit after reviewing SKU compatibility.' );

		self::assertGreaterThan( $scorer->score( $query, $generic ), $scorer->score( $query, $exact ) );
	}

	/**
	 * Exact identifier evidence receives a stronger score than ordinary single-term overlap.
	 */
	public function test_exact_identifier_outranks_generic_single_term_overlap(): void {
		$scorer = new LexicalScorer();
		$query  = new RetrievalQuery( 'sku-42/a guide', array( 'sku-42/a', 'guide' ) );

		$identifier = $this->record( 'c', 'Troubleshooting SKU-42/A hardware.' );
		$generic    = $this->record( 'd', 'General installation guide.' );

		self::assertGreaterThan( $scorer->score( $query, $generic ), $scorer->score( $query, $identifier ) );
	}

	/**
	 * A title boost can improve an otherwise equal match but cannot exceed exact full-query evidence.
	 */
	public function test_title_boost_is_bounded_below_exact_full_query_evidence(): void {
		$scorer = new LexicalScorer();
		$query  = new RetrievalQuery( 'reset controller', array( 'reset', 'controller' ) );

		$exact_body = $this->record( 'e', 'To reset controller safely, disconnect power first.', 'Operations' );
		$title_only = $this->record( 'f', 'Controller maintenance information.', 'Reset controller checklist' );

		self::assertGreaterThan( $scorer->score( $query, $title_only ), $scorer->score( $query, $exact_body ) );
	}

	/**
	 * Equivalent evidence produces equivalent native scores so stable chunk IDs can break ties later.
	 */
	public function test_equivalent_evidence_has_equal_score(): void {
		$scorer = new LexicalScorer();
		$query  = new RetrievalQuery( 'error code', array( 'error', 'code' ) );

		$left  = $this->record( '1', 'Error code reference.' );
		$right = $this->record( '2', 'Error code reference.' );

		self::assertSame( $scorer->score( $query, $left ), $scorer->score( $query, $right ) );
	}

	/**
	 * Build one valid projected chunk fixture.
	 */
	private function record( string $seed, string $content, string $title = 'Example' ): ChunkSearchRecord {
		return new ChunkSearchRecord(
			hash( 'sha256', $seed ),
			'doc-' . $seed,
			7,
			'post',
			$title,
			null,
			$content,
			hash( 'sha256', $content ),
			'en',
			'public',
			0
		);
	}
}
