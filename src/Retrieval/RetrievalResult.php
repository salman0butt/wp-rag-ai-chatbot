<?php
/**
 * Retrieval result contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use InvalidArgumentException;

/**
 * Immutable ordered retrieval candidates plus safe diagnostics.
 */
final readonly class RetrievalResult {
	/**
	 * Validated ordered candidates.
	 *
	 * @var list<RetrievalCandidate>
	 */
	public array $candidates;

	/**
	 * Create one retrieval result.
	 *
	 * @param array          $candidates Ordered retrieval candidates.
	 * @param RetrievalTrace $trace Safe retrieval trace.
	 * @phpstan-param array<array-key, mixed> $candidates
	 * @throws InvalidArgumentException When candidate members are invalid.
	 */
	public function __construct(
		array $candidates,
		public RetrievalTrace $trace
	) {
		$validated_candidates = array();
		foreach ( $candidates as $candidate ) {
			if ( ! $candidate instanceof RetrievalCandidate ) {
				throw new InvalidArgumentException( 'Retrieval result candidate is invalid.' );
			}
			$validated_candidates[] = $candidate;
		}

		$this->candidates = $validated_candidates;
	}
}
