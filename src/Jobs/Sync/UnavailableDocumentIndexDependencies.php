<?php
/**
 * Fail-closed document-index dependency boundary for unreconstructible runtime state.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use WpRagAiChatbot\Indexing\Planning\IndexPlan;
use WpRagAiChatbot\Jobs\JobExecutionException;

/**
 * Explicitly rejects synchronization when current server-side dependencies cannot be rebuilt.
 */
final class UnavailableDocumentIndexDependencies implements DocumentIndexDependencies {
	private const CODE    = 'index_dependencies_unavailable';
	private const MESSAGE = 'Document indexing dependencies are unavailable for this configuration.';

	/**
	 * Fail before planning because reconstruction is unavailable.
	 *
	 * @param DocumentIndexJobPayload $payload Stable identifier-only job payload.
	 * @throws JobExecutionException Always; this configuration cannot execute safely.
	 */
	public function plan( DocumentIndexJobPayload $payload ): IndexPlan {
		unset( $payload );
		throw new JobExecutionException( self::CODE, self::MESSAGE, false );
	}

	/**
	 * Fail closed if execution is invoked without reconstructed dependencies.
	 *
	 * @param DocumentIndexJobPayload $payload Stable identifier-only job payload.
	 * @param IndexPlan               $plan Accepted M07 plan.
	 * @throws JobExecutionException Always; this configuration cannot execute safely.
	 */
	public function execute( DocumentIndexJobPayload $payload, IndexPlan $plan ): void {
		unset( $payload, $plan );
		throw new JobExecutionException( self::CODE, self::MESSAGE, false );
	}
}
