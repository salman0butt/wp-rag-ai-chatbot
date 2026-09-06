<?php
/**
 * Semantic retrieval channel contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Semantic;

use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Produces bounded native semantic candidates for hybrid orchestration.
 */
interface SemanticRetrievalChannel {
	/**
	 * Retrieve semantic candidates within trusted scope.
	 *
	 * @param RetrievalQuery           $query Preprocessed retrieval query.
	 * @param SemanticRetrievalContext $context Trusted semantic retrieval context.
	 * @return list<RankedCandidate>
	 */
	public function retrieve( RetrievalQuery $query, SemanticRetrievalContext $context ): array;
}
