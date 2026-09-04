<?php
/**
 * Chunking domain exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Indexing\Chunking;

use DomainException;

/**
 * Raised when deterministic chunk construction cannot safely continue.
 */
final class ChunkingException extends DomainException {
}
