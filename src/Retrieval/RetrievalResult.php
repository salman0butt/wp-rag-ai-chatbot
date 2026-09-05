<?php
/**
 * Retrieval result contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

/**
 * Immutable ordered retrieval candidates plus safe diagnostics.
 */
final readonly class RetrievalResult {
	/**
	 * Create one retrieval result.
	 *
	 * @param array          $candidates Ordered retrieval candidates.
	 * @param RetrievalTrace $trace Safe retrieval trace.
	 * @phpstan-param list<RetrievalCandidate> $candidates
	 */
	public function __construct(
		public array $candidates,
		public RetrievalTrace $trace
	) {
	}
}
