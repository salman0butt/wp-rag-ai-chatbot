<?php
/**
 * Lexical SQL candidate.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Lexical;

/**
 * One bounded row returned from the lexical candidate stage.
 */
final readonly class LexicalSearchMatch {
	/**
	 * Create one bounded SQL candidate.
	 *
	 * @param ChunkSearchRecord $record Stored chunk projection.
	 */
	public function __construct( public ChunkSearchRecord $record ) {
	}
}
