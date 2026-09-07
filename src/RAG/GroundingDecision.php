<?php
/**
 * Deterministic grounding decision.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\RAG;

use InvalidArgumentException;
use WpRagAiChatbot\Chat\ChatFailureReason;

/**
 * Immutable application-owned decision controlling whether answer generation may run.
 */
final readonly class GroundingDecision {
	/**
	 * Create one grounding decision.
	 *
	 * @param bool                   $may_generate Whether generation is permitted.
	 * @param ChatFailureReason|null $reason Stable denial reason when generation is not permitted.
	 * @param string|null            $no_answer Canonical application-owned no-answer content.
	 * @throws InvalidArgumentException When decision invariants are inconsistent.
	 */
	public function __construct(
		public bool $may_generate,
		public ?ChatFailureReason $reason = null,
		public ?string $no_answer = null
	) {
		if ( $may_generate && ( null !== $reason || null !== $no_answer ) ) {
			throw new InvalidArgumentException( 'Allowed grounding decisions cannot carry denial data.' );
		}

		if ( ! $may_generate && ( ChatFailureReason::INSUFFICIENT_EVIDENCE !== $reason || null === $no_answer || '' === trim( $no_answer ) ) ) {
			throw new InvalidArgumentException( 'Denied grounding decisions require a stable insufficient-evidence result.' );
		}
	}
}
