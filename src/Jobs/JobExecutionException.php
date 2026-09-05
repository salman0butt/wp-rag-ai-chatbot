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
	/**
	 * Return the safe persisted failure code.
	 */
	public function safe_code(): string {
		return '';
	}

	/**
	 * Return the safe persisted failure message.
	 */
	public function safe_message(): string {
		return '';
	}

	/**
	 * Return whether this normalized failure may be retried.
	 */
	public function retryable(): bool {
		return false;
	}
}
