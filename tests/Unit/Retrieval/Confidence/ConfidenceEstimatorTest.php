<?php
/**
 * Retrieval confidence estimator tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Retrieval\Confidence;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Retrieval\ChannelEvidence;
use WpRagAiChatbot\Retrieval\Confidence\ConfidenceEstimator;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Defines deterministic retrieval-confidence behavior for M10.
 */
final class ConfidenceEstimatorTest extends TestCase {
	/**
	 * Agreement between strong semantic and lexical evidence raises confidence.
	 */
	public function test_channel_agreement_increases_confidence(): void {
		$estimator = new ConfidenceEstimator();
		$semantic  = new ChannelEvidence( 'semantic', 0.92, 1, 1.0, 1.0 / 61.0 );
		$lexical   = new ChannelEvidence( 'lexical', 15.0, 1, 1.0, 1.0 / 61.0 );

		$single = $estimator->estimate( $this->candidate( array( $semantic ) ) );
		$agreed = $estimator->estimate( $this->candidate( array( $semantic, $lexical ) ) );

		self::assertGreaterThan( $single->score, $agreed->score );
		self::assertSame( 'high', $agreed->level );
		self::assertGreaterThanOrEqual( 0.0, $agreed->score );
		self::assertLessThanOrEqual( 1.0, $agreed->score );
	}

	/**
	 * Weak tail-only evidence remains explicitly low confidence.
	 */
	public function test_tail_only_evidence_is_low_confidence(): void {
		$estimator = new ConfidenceEstimator();
		$evidence  = new ChannelEvidence( 'semantic', 0.20, 20, 1.0, 1.0 / 80.0 );

		$confidence = $estimator->estimate( $this->candidate( array( $evidence ) ) );

		self::assertSame( 'low', $confidence->level );
		self::assertLessThan( 0.5, $confidence->score );
	}

	/**
	 * Identical evidence always produces the same numeric score and level.
	 */
	public function test_confidence_is_deterministic(): void {
		$estimator = new ConfidenceEstimator();
		$evidence  = new ChannelEvidence( 'semantic', 0.85, 2, 1.0, 1.0 / 62.0 );
		$candidate = $this->candidate( array( $evidence ) );

		$first  = $estimator->estimate( $candidate );
		$second = $estimator->estimate( $candidate );

		self::assertSame( $first->score, $second->score );
		self::assertSame( $first->level, $second->level );
	}

	/**
	 * Create a candidate carrying supplied retrieval evidence.
	 *
	 * @param array $evidence Channel evidence.
	 * @phpstan-param list<ChannelEvidence> $evidence
	 */
	private function candidate( array $evidence ): RetrievalCandidate {
		$fused_score = array_sum(
			array_map(
				static fn ( ChannelEvidence $item ): float => $item->rrf_contribution,
				$evidence
			)
		);

		return new RetrievalCandidate(
			'chunk-confidence',
			'doc-confidence',
			8,
			'Confidence fixture content.',
			'en',
			'public',
			$evidence,
			$fused_score
		);
	}
}
