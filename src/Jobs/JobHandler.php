<?php
/**
 * Typed M09 job handler contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Executes one explicitly registered persisted job type.
 */
interface JobHandler {
	/**
	 * Return the stable persisted job type handled by this implementation.
	 */
	public function type(): string;

	/**
	 * Execute one claimed job using only the current lease context.
	 *
	 * @param JobRecord           $job Current persisted running job.
	 * @param JobExecutionContext $context Current lease execution context.
	 */
	public function handle( JobRecord $job, JobExecutionContext $context ): void;
}
