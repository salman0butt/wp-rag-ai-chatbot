<?php
/**
 * Lexical retrieval channel contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

use WpRagAiChatbot\Retrieval\Fusion\RankedCandidate;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Produces bounded native lexical candidates for hybrid orchestration.
 */
interface LexicalRetrievalChannel {
	/**
	 * Retrieve lexical candidates within trusted scope.
	 *
	 * @param RetrievalQuery $query Preprocessed retrieval query.
	 * @param LexicalFilter  $filter Trusted lexical retrieval filter.
	 * @return list<RankedCandidate>
	 */
	public function retrieve( RetrievalQuery $query, LexicalFilter $filter ): array;
}
