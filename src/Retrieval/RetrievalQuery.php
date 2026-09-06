<?php
/**
 * Normalized retrieval query.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

/**
 * Immutable preprocessed query data.
 */
final readonly class RetrievalQuery {
	/**
	 * Create normalized retrieval query data.
	 *
	 * @param string $normalized Normalized full query.
	 * @param array  $lexical_terms Ordered lexical terms.
	 * @phpstan-param list<string> $lexical_terms
	 */
	public function __construct(
		public string $normalized,
		public array $lexical_terms
	) {
	}
}
