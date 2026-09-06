<?php
/**
 * Deterministic retrieval confidence estimator.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Confidence;

use WpRagAiChatbot\Retrieval\ChannelEvidence;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Estimate bounded confidence from retrieval rank evidence and channel agreement.
 */
final class ConfidenceEstimator {
	/**
	 * Estimate deterministic confidence for one fused candidate.
	 *
	 * This signal measures retrieval strength only; it is not an answer-truth probability.
	 *
	 * @param RetrievalCandidate $candidate Fused retrieval candidate.
	 * @return RetrievalConfidence
	 */
	public function estimate( RetrievalCandidate $candidate ): RetrievalConfidence {
		if ( array() === $candidate->channel_evidence ) {
			return new RetrievalConfidence( 0.0, 'low' );
		}

		$best_rank = min(
			array_map(
				static fn ( ChannelEvidence $evidence ): int => $evidence->rank,
				$candidate->channel_evidence
			)
		);

		$channels = array();
		foreach ( $candidate->channel_evidence as $evidence ) {
			$channels[ $evidence->channel ] = true;
		}

		$rank_signal     = 1.0 / $best_rank;
		$agreement_bonus = count( $channels ) > 1 ? 0.35 : 0.0;
		$score           = min( 1.0, ( 0.65 * $rank_signal ) + $agreement_bonus );

		$level = match ( true ) {
			$score >= 0.75 => 'high',
			$score >= 0.50 => 'medium',
			default        => 'low',
		};

		return new RetrievalConfidence( $score, $level );
	}
}
