<?php
/**
 * Grounding policy contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\RAG;

use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Decides generation eligibility from application-owned mode and selected retrieval evidence.
 */
interface GroundingPolicy {
	/**
	 * Decide whether answer generation may run.
	 *
	 * @param GroundingMode $mode Grounding mode.
	 * @param array         $selected_candidates Final bounded retrieval candidates.
	 * @phpstan-param list<RetrievalCandidate> $selected_candidates
	 */
	public function decide( GroundingMode $mode, array $selected_candidates ): GroundingDecision;
}
