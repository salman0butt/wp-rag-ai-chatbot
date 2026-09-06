<?php
/**
 * Post-fusion candidate access policy contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Access;

use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Applies trusted server-side scope to one fused retrieval candidate.
 */
interface CandidateAccessPolicy {
	/**
	 * Determine whether a fused candidate remains inside trusted scope.
	 *
	 * @param RetrievalCandidate $candidate Fused candidate.
	 * @param RetrievalFilter    $filter Trusted server-side retrieval constraints.
	 */
	public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool;
}
