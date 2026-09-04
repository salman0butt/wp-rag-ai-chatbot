<?php
/**
 * Vector write result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Immutable write outcome.
 */
final class VectorWriteResult {
	/**
	 * Create a write result.
	 *
	 * @param bool $changed Whether persistence changed.
	 */
	public function __construct( public readonly bool $changed ) {
	}
}
