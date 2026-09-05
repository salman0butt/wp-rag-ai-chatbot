<?php
/**
 * Typed M09 job execution failure.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use RuntimeException;

/**
 * Represents a handler failure whose retry semantics are defined by Task 3.
 */
final class JobExecutionException extends RuntimeException {
}
