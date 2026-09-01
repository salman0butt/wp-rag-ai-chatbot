<?php
/**
 * Database exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database;

use RuntimeException;

/**
 * Represents a persistence or migration failure.
 */
final class DatabaseException extends RuntimeException {
}
