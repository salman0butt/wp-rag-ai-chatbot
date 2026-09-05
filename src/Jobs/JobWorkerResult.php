<?php
/**
 * Immutable M09 worker invocation result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Reports bounded worker activity without exposing queue internals.
 */
final class JobWorkerResult {
	/**
	 * Create one worker result.
	 *
	 * @param int $started_jobs Number of jobs started by this invocation.
	 */
	public function __construct( public readonly int $started_jobs ) {
	}
}
