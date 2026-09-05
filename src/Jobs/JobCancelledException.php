<?php
/**
 * Cooperative M09 cancellation signal.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use RuntimeException;

/**
 * Signals that a handler stopped because cooperative cancellation was requested.
 */
final class JobCancelledException extends RuntimeException {
}
