<?php
/**
 * Queue boundary for knowledge-source synchronization.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\WordPressJobCron;

/** Persists one source-sync job and wakes the existing WordPress worker hook. */
final class KnowledgeSourceSyncJobEnqueuer {
	public const TYPE = 'sync.source';

	/**
	 * Create the source-sync enqueue boundary.
	 *
	 * @param JobRepository $repository Durable queue repository.
	 * @param Clock         $clock Queue clock.
	 */
	public function __construct(
		private readonly JobRepository $repository,
		private readonly Clock $clock
	) {
	}

	/**
	 * Enqueue one source generation without performing source or provider work.
	 *
	 * @param KnowledgeSourceSyncJobPayload $payload Validated source identifiers.
	 */
	public function enqueue( KnowledgeSourceSyncJobPayload $payload ): JobRecord {
		$now      = $this->clock->now();
		$identity = implode(
			'|',
			array(
				(string) $payload->source_id,
				$payload->collection_id,
				$payload->configuration_id,
				$payload->generation,
			)
		);
		$record   = $this->repository->enqueue(
			new JobRequest(
				self::TYPE,
				$payload->to_array(),
				'sync-source-' . hash( 'sha256', $identity ),
				3
			),
			$now
		);

		wp_schedule_single_event( $now->getTimestamp(), WordPressJobCron::HOOK );

		return $record;
	}
}
