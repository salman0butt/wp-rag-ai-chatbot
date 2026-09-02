<?php
/**
 * Knowledge source domain exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Knowledge\Sources;

use RuntimeException;

/**
 * Raised when source registration or normalization invariants are violated.
 */
final class KnowledgeSourceException extends RuntimeException {
}
