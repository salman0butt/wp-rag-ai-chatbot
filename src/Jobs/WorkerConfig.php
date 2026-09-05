<?php
/**
 * Bounded M09 worker configuration contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Holds worker bounds once Task 3 behavior is defined by tests.
 */
final class WorkerConfig {
	/** Maximum jobs one worker invocation may start. */
	public readonly int $max_jobs;

	/** Maximum claim/start budget in seconds. */
	public readonly int $start_budget_seconds;

	/** Lease duration in seconds. */
	public readonly int $lease_seconds;

	/**
	 * Create the worker configuration boundary.
	 */
	public function __construct() {
	}
}
