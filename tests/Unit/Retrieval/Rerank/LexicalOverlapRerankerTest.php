<?php
/**
 * Deterministic lexical overlap reranker tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Rerank;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Rerank\LexicalOverlapReranker;
use WpRagAiChatbot\Retrieval\Rerank\RerankRequest;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Defines the offline deterministic reranking baseline.
 */
final class LexicalOverlapRerankerTest extends TestCase {
	/**
	 * Stronger lexical overlap deterministically outranks weaker overlap.
	 */
	public function test_lexical_overlap_reorders_candidates_deterministically(): void {
		$request = new RerankRequest(
			new RetrievalQuery( 'refund policy', array( 'refund', 'policy' ) ),
			array(
				$this->candidate( 'weak', 'Shipping policy and delivery timing.', 0.9 ),
				$this->candidate( 'strong', 'Our refund policy allows returns within 30 days.', 0.8 ),
			)
		);

		$result = ( new LexicalOverlapReranker() )->rerank( $request );

		self::assertSame( array( 'strong', 'weak' ), array_keys( $result->scores ) );
		self::assertGreaterThan( $result->scores['weak'], $result->scores['strong'] );
	}

	/**
	 * Equal local overlap retains deterministic supplied order.
	 */
	public function test_equal_overlap_preserves_supplied_order(): void {
		$request = new RerankRequest(
			new RetrievalQuery( 'refund', array( 'refund' ) ),
			array(
				$this->candidate( 'chunk-b', 'Refund available.', 0.7 ),
				$this->candidate( 'chunk-a', 'Refund accepted.', 0.6 ),
			)
		);

		$result = ( new LexicalOverlapReranker() )->rerank( $request );

		self::assertSame( array( 'chunk-b', 'chunk-a' ), array_keys( $result->scores ) );
	}

	/**
	 * Create one candidate fixture.
	 *
	 * @param string $id Stable chunk ID.
	 * @param string $content Untrusted chunk content.
	 * @param float  $score Fused score.
	 */
	private function candidate( string $id, string $content, float $score ): RetrievalCandidate {
		return new RetrievalCandidate( $id, 'doc-' . $id, 8, $content, 'en', 'public', array(), $score );
	}
}
