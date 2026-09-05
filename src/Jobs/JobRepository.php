<?php
/**
 * Persisted job repository contract.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use DateTimeImmutable;

/**
 * Persistence boundary for the durable queue.
 *
 * Later M09 tasks extend this contract with lease and transition operations.
 */
interface JobRepository {
	/**
	 * Enqueue one validated job request for the supplied UTC time.
	 *
	 * @param JobRequest        $request Validated queue request.
	 * @param DateTimeImmutable $now Current UTC time.
	 */
	public function enqueue( JobRequest $request, DateTimeImmutable $now ): JobRecord;
}
