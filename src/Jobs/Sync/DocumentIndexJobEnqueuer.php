<?php
/**
 * M09 queue boundary for document-index synchronization.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use DateTimeImmutable;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;

/**
 * Enqueues one active synchronization generation with deterministic deduplication.
 */
final class DocumentIndexJobEnqueuer {
	/** Stable allowlisted job type. */
	public const TYPE = 'index.document';

	/**
	 * Create the synchronization enqueue boundary.
	 *
	 * @param JobRepository $repository Durable queue repository.
	 */
	public function __construct( private readonly JobRepository $repository ) {
	}

	/**
	 * Enqueue one identifier-only synchronization generation.
	 *
	 * @param DocumentIndexJobPayload $payload Validated synchronization payload.
	 * @param DateTimeImmutable       $now Current UTC time.
	 */
	public function enqueue( DocumentIndexJobPayload $payload, DateTimeImmutable $now ): JobRecord {
		$identity = implode(
			'|',
			array(
				$payload->document_key,
				(string) $payload->source_id,
				$payload->collection_id,
				$payload->configuration_id,
				$payload->generation,
			)
		);

		$request = new JobRequest(
			self::TYPE,
			$payload->to_array(),
			'index-document-' . hash( 'sha256', $identity ),
			3
		);

		return $this->repository->enqueue( $request, $now );
	}
}
