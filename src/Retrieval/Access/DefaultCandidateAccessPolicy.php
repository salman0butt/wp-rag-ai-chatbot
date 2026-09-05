<?php
/**
 * Default fail-closed post-fusion candidate access policy.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Access;

use WpRagAiChatbot\Retrieval\Filter\RetrievalFilter;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;

/**
 * Rechecks fused candidate lineage against trusted retrieval scope.
 */
final readonly class DefaultCandidateAccessPolicy implements CandidateAccessPolicy {
	/**
	 * Determine whether a fused candidate remains inside trusted scope.
	 *
	 * @param RetrievalCandidate $candidate Fused candidate.
	 * @param RetrievalFilter    $filter Trusted server-side retrieval constraints.
	 */
	public function allows( RetrievalCandidate $candidate, RetrievalFilter $filter ): bool {
		if ( null !== $filter->visibility && $candidate->visibility !== $filter->visibility ) {
			return false;
		}
		if ( null !== $filter->language && $candidate->language !== $filter->language ) {
			return false;
		}
		if ( array() !== $filter->source_ids && ! in_array( $candidate->source_id, $filter->source_ids, true ) ) {
			return false;
		}
		if ( array() !== $filter->document_keys && ! in_array( $candidate->document_id, $filter->document_keys, true ) ) {
			return false;
		}

		return true;
	}
}
