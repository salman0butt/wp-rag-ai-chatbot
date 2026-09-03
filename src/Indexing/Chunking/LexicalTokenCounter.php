<?php
/**
 * Deterministic lexical token counter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Chunking;

use DomainException;

/**
 * Counts Unicode letter/number runs and individual punctuation/symbol units.
 */
final class LexicalTokenCounter implements TokenCounter {
	/**
	 * Count deterministic lexical units without provider coupling.
	 *
	 * @param string $text Text to count.
	 * @throws DomainException When text is not valid UTF-8.
	 */
	public function count( string $text ): int {
		$count = preg_match_all( '/[\p{L}\p{N}]+|[^\s\p{L}\p{N}]/u', $text, $matches );

		if ( false === $count ) {
			throw new DomainException( 'Token counting requires valid UTF-8 text.' );
		}

		return $count;
	}
}
