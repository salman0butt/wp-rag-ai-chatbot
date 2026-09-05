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
	/** @var list<RetrievalCandidate> */
	public array $candidates;

	/**
	 * Create one retrieval result.
	 *
	 * @param array          $candidates Ordered retrieval candidates.
	 * @param RetrievalTrace $trace Safe retrieval trace.
	 * @throws InvalidArgumentException When candidate members are invalid.
	 */
	public function __construct(
		array $candidates,
		public RetrievalTrace $trace
	) {
		foreach ( $candidates as $candidate ) {
			if ( ! $candidate instanceof RetrievalCandidate ) {
				throw new InvalidArgumentException( 'Retrieval result candidate is invalid.' );
			}
		}

		/** @var list<RetrievalCandidate> $candidates */
		$this->candidates = $candidates;
	}
}
