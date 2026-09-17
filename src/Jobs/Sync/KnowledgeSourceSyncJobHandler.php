<?php
/**
 * Queued knowledge-source synchronization handler.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use InvalidArgumentException;
use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Documents\DocumentRecord;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobCancelledException;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\JobHandler;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceException;
use WpRagAiChatbot\Knowledge\Sources\KnowledgeSourceRegistry;

// phpcs:disable WordPress.NamingConventions -- Existing domain DTO properties use the approved camelCase contract.
/** Normalizes one persisted source and queues the existing document-index algorithm. */
final class KnowledgeSourceSyncJobHandler implements JobHandler {
	private const MAX_DOCUMENTS = 1000;

	/**
	 * Create the source-sync handler.
	 *
	 * @param KnowledgeSourceRepository $sources Persisted source repository.
	 * @param KnowledgeSourceRegistry   $registry Registered source normalizers.
	 * @param DocumentRepository        $documents Canonical document repository.
	 * @param DocumentIndexJobEnqueuer  $document_jobs Existing document-index enqueue boundary.
	 * @param Clock                     $clock Worker clock.
	 */
	public function __construct(
		private readonly KnowledgeSourceRepository $sources,
		private readonly KnowledgeSourceRegistry $registry,
		private readonly DocumentRepository $documents,
		private readonly DocumentIndexJobEnqueuer $document_jobs,
		private readonly Clock $clock
	) {
	}

	/** Return the stable persisted source-sync type. */
	public function type(): string {
		return KnowledgeSourceSyncJobEnqueuer::TYPE;
	}

	/**
	 * Execute one bounded source synchronization.
	 *
	 * @param JobRecord           $job Claimed source-sync job.
	 * @param JobExecutionContext $context Current lease context.
	 * @throws JobExecutionException When current persisted state cannot be synchronized safely.
	 */
	public function handle( JobRecord $job, JobExecutionContext $context ): void {
		$payload = KnowledgeSourceSyncJobPayload::from_array( $job->payload );

		try {
			$this->throw_if_cancelled( $context );
			$this->update_progress( $context, new JobProgress( 0, 1, 'Normalizing knowledge source' ) );
			$source = $this->sources->findById( $payload->source_id );
			if ( null === $source ) {
				throw new JobExecutionException( 'source_sync_not_found', 'Knowledge source is no longer available.', false );
			}
			if ( ! WordPressDocumentIndexDependencies::matches_source_configuration( $source, $payload ) ) {
				throw new JobExecutionException(
					'source_sync_configuration_mismatch',
					'Knowledge source indexing configuration has changed.',
					false
				);
			}

			$normalized = array();
			foreach ( $this->registry->get( $source->sourceType )->documents( $source ) as $document ) {
				if ( $document->sourceId !== $payload->source_id ) {
					throw new JobExecutionException( 'source_sync_invalid', 'Knowledge source returned invalid document lineage.', false );
				}
				if ( count( $normalized ) >= self::MAX_DOCUMENTS ) {
					throw new JobExecutionException( 'source_sync_invalid', 'Knowledge source exceeds the bounded synchronization limit.', false );
				}
				$normalized[] = $document;
			}

			$total = max( 1, count( $normalized ) );
			foreach ( $normalized as $index => $document ) {
				$context->heartbeat();
				$this->throw_if_cancelled( $context );
				$saved = $this->documents->save( $this->record_for_save( $document ) );
				try {
					$index_payload = new DocumentIndexJobPayload(
						$saved->documentKey,
						$payload->source_id,
						$payload->collection_id,
						$payload->configuration_id,
						$payload->generation
					);
				} catch ( JobQueueException ) {
					throw new JobExecutionException( 'source_sync_document_queue_invalid', 'WordPress content produced an invalid document queue payload.', false );
				}
				try {
					$this->document_jobs->enqueue(
						$index_payload,
						$this->clock->now()
					);
				} catch ( JobQueueException $error ) {
					$failure_code = $this->child_queue_failure_code( $error );
					throw new JobExecutionException(
						$failure_code,
						'source_sync_child_lock_unavailable' === $failure_code
							? 'WordPress content indexing is temporarily busy.'
							: 'WordPress content could not be queued for indexing.',
						false
					);
				}
				$this->update_progress( $context, new JobProgress( $index + 1, $total, 'Queued documents for indexing' ) );
			}

			if ( array() === $normalized ) {
				$this->update_progress( $context, new JobProgress( 1, 1, 'Knowledge source synchronization complete' ) );
			}
		} catch ( DatabaseException ) {
			throw new JobExecutionException( 'source_sync_unavailable', 'Knowledge source persistence is temporarily unavailable.', true );
		} catch ( KnowledgeSourceException ) {
			throw new JobExecutionException( 'source_sync_content_invalid', 'WordPress content could not be normalized safely.', false );
		} catch ( JobQueueException $error ) {
			$failure_code = $this->source_queue_failure_code( $error );
			if ( 'source_sync_heartbeat_unavailable' === $failure_code ) {
				throw new JobExecutionException( 'source_sync_heartbeat_unavailable', 'WordPress content synchronization lease could not be renewed.', false );
			}
			throw new JobExecutionException( 'source_sync_queue_invalid', 'WordPress content could not be queued for indexing.', false );
		} catch ( InvalidArgumentException ) {
			throw new JobExecutionException( 'source_sync_document_invalid', 'WordPress content produced an invalid document.', false );
		}
	}

	/**
	 * Translate child queue state failures into stable diagnostic categories.
	 *
	 * @param JobQueueException $error Child queue failure.
	 */
	private function child_queue_failure_code( JobQueueException $error ): string {
		return str_contains( strtolower( $error->getMessage() ), 'lock' )
			? 'source_sync_child_lock_unavailable'
			: 'source_sync_child_queue_failure';
	}

	/**
	 * Translate source-worker queue state failures into stable diagnostic categories.
	 *
	 * @param JobQueueException $error Source-worker queue failure.
	 */
	private function source_queue_failure_code( JobQueueException $error ): string {
		return str_contains( strtolower( $error->getMessage() ), 'heartbeat' )
			? 'source_sync_heartbeat_unavailable'
			: 'source_sync_queue_invalid';
	}

	/**
	 * Persist progress failures as a safe source-sync boundary error.
	 *
	 * @param JobExecutionContext $context Current lease context.
	 * @param JobProgress         $progress Progress snapshot.
	 * @throws JobExecutionException When progress persistence fails.
	 */
	private function update_progress( JobExecutionContext $context, JobProgress $progress ): void {
		try {
			$context->update_progress( $progress );
		} catch ( JobQueueException ) {
			throw new JobExecutionException( 'source_sync_progress_unavailable', 'WordPress content synchronization progress could not be saved.', false );
		}
	}

	/**
	 * Preserve an existing document identity and creation time during normalization updates.
	 *
	 * @param DocumentRecord $document Newly normalized document.
	 * @throws KnowledgeSourceException When an existing key belongs to another source.
	 */
	private function record_for_save( DocumentRecord $document ): DocumentRecord {
		$current = $this->documents->findByKey( $document->documentKey );
		if ( null === $current ) {
			return $document;
		}
		if ( $current->sourceId !== $document->sourceId ) {
			throw new KnowledgeSourceException( 'Knowledge document key belongs to another source.' );
		}

		return new DocumentRecord(
			$current->id,
			$document->documentKey,
			$document->sourceId,
			$document->externalId,
			$document->documentType,
			$document->title,
			$document->canonicalUrl,
			$document->content,
			$document->metadata,
			$document->sourceVersion,
			$document->contentHash,
			$document->language,
			$document->visibility,
			$current->createdAt,
			$document->updatedAt
		);
	}

	/**
	 * Abort when cancellation is requested.
	 *
	 * @param JobExecutionContext $context Current lease context.
	 * @throws JobCancelledException When cancellation is requested.
	 */
	private function throw_if_cancelled( JobExecutionContext $context ): void {
		if ( $context->cancellation_requested() ) {
			throw new JobCancelledException( 'Knowledge source synchronization was cancelled.' );
		}
	}
}
// phpcs:enable WordPress.NamingConventions
