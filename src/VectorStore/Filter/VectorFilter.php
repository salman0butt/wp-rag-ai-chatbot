<?php
/**
 * Portable vector metadata filter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore\Filter;

/**
 * Matches validated portable metadata without vendor expressions.
 */
interface VectorFilter {
	/**
	 * Determine whether metadata matches.
	 *
	 * @param array<string, scalar> $metadata Portable metadata.
	 */
	public function matches( array $metadata ): bool;
}
