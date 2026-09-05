<?php
/**
 * Job queue domain exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use InvalidArgumentException;

/**
 * Signals invalid queue contracts and state transitions.
 */
final class JobQueueException extends InvalidArgumentException {
}
