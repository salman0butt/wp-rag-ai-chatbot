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
	 * @param string       $normalized Normalized full query.
	 * @param list<string> $lexical_terms Ordered lexical terms.
	 */
	public function __construct(
		public string $normalized,
		public array $lexical_terms
	) {
	}
}
