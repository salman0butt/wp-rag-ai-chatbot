<?php
/**
 * Bounded reranker request contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Rerank;

use InvalidArgumentException;
use WpRagAiChatbot\Retrieval\RetrievalCandidate;
use WpRagAiChatbot\Retrieval\RetrievalQuery;

/**
 * Immutable query plus already access-approved candidates for one rerank pass.
 */
final readonly class RerankRequest {
	/**
	 * Validated candidates in fused order.
	 *
	 * @var list<RetrievalCandidate>
	 */
	public array $candidates;

	/**
	 * Create one bounded rerank request.
	 *
	 * @param RetrievalQuery $query Preprocessed query.
	 * @param array          $candidates Access-approved candidates.
	 * @phpstan-param array<array-key, mixed> $candidates
	 * @throws InvalidArgumentException When candidate members or identifiers are invalid.
	 */
	public function __construct(
		public RetrievalQuery $query,
		array $candidates
	) {
		$validated = array();
		$seen      = array();

		foreach ( $candidates as $candidate ) {
			if ( ! $candidate instanceof RetrievalCandidate ) {
				throw new InvalidArgumentException( 'Rerank request candidate is invalid.' );
			}
			if ( isset( $seen[ $candidate->chunk_id ] ) ) {
				throw new InvalidArgumentException( 'Rerank request candidate IDs must be unique.' );
			}

			$seen[ $candidate->chunk_id ] = true;
			$validated[]                  = $candidate;
		}

		$this->candidates = $validated;
	}
}
