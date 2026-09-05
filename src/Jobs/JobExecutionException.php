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
 * Represents a normalized handler failure safe for queue persistence.
 */
final class JobExecutionException extends RuntimeException {
	/**
	 * Create one normalized execution failure.
	 *
	 * @param string $safe_code Stable sanitized failure code.
	 * @param string $safe_message Sanitized persisted failure message.
	 * @param bool   $retryable Whether the worker may retry while attempts remain.
	 * @throws JobQueueException When persisted diagnostic fields are invalid.
	 */
	public function __construct(
		private readonly string $safe_code,
		private readonly string $safe_message,
		private readonly bool $retryable
	) {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_.-]{0,99}$/', $safe_code ) ) {
			throw new JobQueueException( 'Job execution failure code must use the stable lowercase queue grammar.' );
		}
		if ( '' === $safe_message || strlen( $safe_message ) > 500 || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $safe_message ) ) {
			throw new JobQueueException( 'Job execution failure message must be bounded sanitized text.' );
		}

		parent::__construct( $safe_message );
	}

	/**
	 * Return the safe persisted failure code.
	 */
	public function safe_code(): string {
		return $this->safe_code;
	}

	/**
	 * Return the safe persisted failure message.
	 */
	public function safe_message(): string {
		return $this->safe_message;
	}

	/**
	 * Return whether this normalized failure may be retried.
	 */
	public function retryable(): bool {
		return $this->retryable;
	}
}
