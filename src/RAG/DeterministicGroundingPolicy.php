<?php
/**
 * Deterministic grounding policy.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\RAG;

use InvalidArgumentException;
use WpRagAiChatbot\Chat\ChatFailureReason;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Applies fail-closed strict grounding using existing deterministic M10 confidence levels.
 */
final class DeterministicGroundingPolicy implements GroundingPolicy {
	private const MAX_SELECTED_CANDIDATES = 12;

	private const STRICT_NO_ANSWER = "I don't have enough reliable information in the selected sources to answer that.";

	/**
	 * {@inheritDoc}
	 */
	public function decide( GroundingMode $mode, array $selected_candidates ): GroundingDecision {
		if ( count( $selected_candidates ) > self::MAX_SELECTED_CANDIDATES ) {
			throw new InvalidArgumentException( 'Grounding candidate limit exceeded.' );
		}

		foreach ( $selected_candidates as $candidate ) {
			if ( ! $candidate instanceof RetrievalCandidate ) {
				throw new InvalidArgumentException( 'Grounding candidates are invalid.' );
			}
		}

		if ( GroundingMode::ASSISTED === $mode ) {
			return new GroundingDecision( true );
		}

		foreach ( $selected_candidates as $candidate ) {
			if ( null !== $candidate->confidence && in_array( $candidate->confidence->level, array( 'medium', 'high' ), true ) ) {
				return new GroundingDecision( true );
			}
		}

		return new GroundingDecision(
			false,
			ChatFailureReason::INSUFFICIENT_EVIDENCE,
			self::STRICT_NO_ANSWER
		);
	}
}
