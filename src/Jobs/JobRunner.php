<?php
/**
 * Shared bounded job runner contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Executes one bounded worker invocation.
 */
interface JobRunner {
	/**
	 * Run jobs within the supplied validated bounds.
	 *
	 * @param WorkerConfig $config Worker bounds.
	 */
	public function run( WorkerConfig $config ): JobWorkerResult;
}
