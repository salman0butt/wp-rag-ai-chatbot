<?php
/**
 * Reranker contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Rerank;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\Rerank\NoOpReranker;
use WpRagAiChatbot\Retrieval\Rerank\RerankRequest;
use WpRagAiChatbot\Retrieval\Rerank\RerankResult;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Defines bounded immutable reranker request/result behavior.
 */
final class RerankerContractTest extends TestCase {
	/**
	 * No-op reranking preserves the supplied order and fused scores.
	 */
	public function test_noop_reranker_preserves_order_and_scores(): void {
		$request = new RerankRequest(
			new RetrievalQuery( 'refund policy', array( 'refund', 'policy' ) ),
			array( $this->candidate( 'chunk-b', 0.4 ), $this->candidate( 'chunk-a', 0.3 ) )
		);

		$result = ( new NoOpReranker() )->rerank( $request );

		self::assertSame( array( 'chunk-b', 'chunk-a' ), array_keys( $result->scores ) );
		self::assertSame( array( 0.4, 0.3 ), array_values( $result->scores ) );
	}

	/**
	 * Request members must be retrieval candidates.
	 */
	public function test_request_rejects_non_candidate_members(): void {
		$this->expectException( InvalidArgumentException::class );
		new RerankRequest(
			new RetrievalQuery( 'refund policy', array( 'refund', 'policy' ) ),
			array( 'not-a-candidate' )
		);
	}

	/**
	 * Rerank results reject empty identifiers.
	 */
	public function test_result_rejects_empty_candidate_identifier(): void {
		$this->expectException( InvalidArgumentException::class );
		new RerankResult( array( '' => 0.8 ) );
	}

	/**
	 * Rerank results reject non-finite scores.
	 */
	public function test_result_rejects_non_finite_score(): void {
		$this->expectException( InvalidArgumentException::class );
		new RerankResult( array( 'chunk-a' => INF ) );
	}

	/**
	 * Create one access-approved candidate fixture.
	 *
	 * @param string $id Stable chunk identifier.
	 * @param float  $score Fused score.
	 */
	private function candidate( string $id, float $score ): RetrievalCandidate {
		return new RetrievalCandidate( $id, 'doc-' . $id, 8, 'content-' . $id, 'en', 'public', array(), $score );
	}
}
