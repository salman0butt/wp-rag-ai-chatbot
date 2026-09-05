<?php
/**
 * Server-side reconstruction boundary for queued document indexing.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use WpRagAiChatbot\Indexing\Planning\IndexPlan;

/**
 * Resolves current persistent state and delegates accepted plan execution.
 */
interface DocumentIndexDependencies {
	/**
	 * Reconstruct current state and build the accepted M07 plan.
	 *
	 * @param DocumentIndexJobPayload $payload Stable identifier-only job payload.
	 */
	public function plan( DocumentIndexJobPayload $payload ): IndexPlan;

	/**
	 * Execute one accepted plan through the configured M08 boundary.
	 *
	 * @param DocumentIndexJobPayload $payload Stable identifier-only job payload.
	 * @param IndexPlan               $plan Accepted M07 plan.
	 */
	public function execute( DocumentIndexJobPayload $payload, IndexPlan $plan ): void;
}
