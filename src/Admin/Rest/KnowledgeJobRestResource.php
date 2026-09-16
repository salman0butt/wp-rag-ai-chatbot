<?php
/**
 * Administrator knowledge job status and lifecycle resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Documents\DocumentRepository;
use WpRagAiChatbot\Jobs\Sync\WordPressDocumentIndexDependencies;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobReadRepository;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;
use WpRagAiChatbot\Knowledge\KnowledgeSourceRepository;

// phpcs:disable WordPress.NamingConventions -- DTO keys and repository API follow the approved domain contract.
/**
 * Projects bounded job status and delegates lifecycle mutations to the M09 queue seams.
 */
final class KnowledgeJobRestResource {
	/**
	 * Create the resource.
	 *
	 * @param JobReadRepository         $reader Read-only job inspection repository.
	 * @param JobRepository             $repository Existing M09 mutation repository.
	 * @param Clock                     $clock Queue clock.
	 * @param KnowledgeSourceRepository $sources Persisted source repository.
	 * @param DocumentRepository        $documents Persisted document repository.
	 */
	public function __construct(
		private readonly JobReadRepository $reader,
		private readonly JobRepository $repository,
		private readonly Clock $clock,
		private readonly KnowledgeSourceRepository $sources,
		private readonly DocumentRepository $documents
	) {
	}

	/**
	 * Return one bounded page of safe job fields.
	 *
	 * @param int $page One-based page.
	 * @param int $perPage Requested page size.
	 * @return array<string,mixed>
	 */
	public function list( int $page, int $perPage ): array {
		if ( $page < 1 || $perPage < 1 || $perPage > 100 ) {
			return self::error( 'invalid_request', 'Request parameters are invalid.' );
		}

		$result = $this->reader->paginate( $page, $perPage );

		return array(
			'items'    => array_map( self::project( ... ), $result->items ),
			'total'    => $result->total,
			'page'     => $result->page,
			'per_page' => $result->perPage,
		);
	}

	/**
	 * Enqueue a new document-index generation through the established M09 enqueuer.
	 *
	 * @param array<string,mixed> $payload Identifier-only document-index payload.
	 * @return array<string,mixed>
	 */
	public function enqueue( array $payload ): array {
		try {
			$identifiers  = $this->request_identifiers( $payload );
			$safe_payload = $this->server_owned_payload( $identifiers );
			$record       = ( new DocumentIndexJobEnqueuer( $this->repository ) )->enqueue( $safe_payload, $this->clock->now() );
		} catch ( DatabaseException | JobQueueException ) {
			return self::error( 'invalid_request', 'Request parameters are invalid.' );
		}

		return self::project( $record );
	}

	/**
	 * Request cancellation only for M09 states that support cancellation.
	 *
	 * @param string $job_key Stable job identity.
	 * @return array<string,mixed>
	 */
	public function cancel( string $job_key ): array {
		$record = $this->reader->findByKey( $job_key );
		if ( null === $record ) {
			return self::error( 'not_found', 'Job was not found.' );
		}
		if ( $record->status->terminal() ) {
			return self::error( 'invalid_transition', 'The requested job transition is not allowed.' );
		}

		return self::project( $this->repository->requestCancellation( $job_key, $this->clock->now() ) );
	}

	/**
	 * Retry one failed document-index job as a fresh M09 generation.
	 *
	 * @param string $job_key Stable job identity.
	 * @return array<string,mixed>
	 */
	public function retry( string $job_key ): array {
		$record = $this->reader->findByKey( $job_key );
		if ( null === $record ) {
			return self::error( 'not_found', 'Job was not found.' );
		}
		if ( JobStatus::FAILED !== $record->status || 'index.document' !== $record->type ) {
			return self::error( 'invalid_transition', 'The requested job transition is not allowed.' );
		}

		try {
			$payload = $this->server_owned_payload( $this->persisted_identifiers( $record->payload ) );
			$retry   = ( new DocumentIndexJobEnqueuer( $this->repository ) )->enqueue( $payload, $this->clock->now() );
		} catch ( DatabaseException | JobQueueException ) {
			return self::error( 'invalid_transition', 'The requested job transition is not allowed.' );
		}

		return self::project( $retry );
	}

	/**
	 * Accept only the browser-owned document/source identifiers for a new enqueue request.
	 *
	 * @param array<string,mixed> $payload Raw REST payload.
	 * @return array{document_key:string,source_id:int}
	 * @throws JobQueueException When browser-supplied indexing metadata is present.
	 */
	private function request_identifiers( array $payload ): array {
		$expected = array( 'document_key', 'source_id' );
		$actual   = array_keys( $payload );
		sort( $expected );
		sort( $actual );

		if ( $expected !== $actual || ! is_string( $payload['document_key'] ) || ! is_int( $payload['source_id'] ) ) {
			throw new JobQueueException( 'Document indexing requests must not provide server-owned metadata.' );
		}

		return array(
			'document_key' => $payload['document_key'],
			'source_id'    => $payload['source_id'],
		);
	}

	/**
	 * Extract only the stable identifiers from a persisted legacy payload before re-deriving it.
	 *
	 * @param array<string,mixed> $payload Persisted queue payload.
	 * @return array{document_key:string,source_id:int}
	 * @throws JobQueueException When persisted lineage identifiers are missing or malformed.
	 */
	private function persisted_identifiers( array $payload ): array {
		return $this->request_identifiers(
			array(
				'document_key' => $payload['document_key'] ?? null,
				'source_id'    => $payload['source_id'] ?? null,
			)
		);
	}

	/**
	 * Resolve lineage and rebuild the exact current fixed-profile document-index payload.
	 *
	 * @param array{document_key:string,source_id:int} $identifiers Browser/persisted lineage identifiers.
	 * @throws DatabaseException|JobQueueException When persisted lineage or source semantics cannot produce a current job.
	 */
	private function server_owned_payload( array $identifiers ): DocumentIndexJobPayload {
		$document = $this->documents->findByKey( $identifiers['document_key'] );
		$source   = $this->sources->findById( $identifiers['source_id'] );
		if ( null === $document || null === $source || $document->sourceId !== $identifiers['source_id'] ) {
			throw new JobQueueException( 'Document indexing lineage is invalid.' );
		}

		$generation = $source->sourceHash;
		$semantic   = $source->config['semantic_retrieval'] ?? null;
		$expected   = WordPressDocumentIndexDependencies::semantic_configuration();
		if ( null === $generation || '' === $generation || ! is_array( $semantic ) || count( $semantic ) !== count( $expected ) ) {
			throw new JobQueueException( 'Document indexing source state is invalid.' );
		}
		foreach ( $expected as $key => $value ) {
			if ( ! array_key_exists( $key, $semantic ) || $semantic[ $key ] !== $value ) {
				throw new JobQueueException( 'Document indexing source state is invalid.' );
			}
		}

		$payload = new DocumentIndexJobPayload(
			$identifiers['document_key'],
			$identifiers['source_id'],
			(string) $semantic['collection_id'],
			(string) $semantic['configuration_id'],
			$generation
		);
		if ( ! WordPressDocumentIndexDependencies::matches_source_configuration( $source, $payload ) ) {
			throw new JobQueueException( 'Document indexing source state is stale.' );
		}

		return $payload;
	}

	/**
	 * Project only non-secret, operationally useful job fields.
	 *
	 * @param JobRecord $record Persisted job record.
	 * @return array<string,mixed>
	 */
	private static function project( JobRecord $record ): array {
		return array(
			'job_key'             => $record->job_key,
			'type'                => $record->type,
			'status'              => $record->status->value,
			'attempts'            => $record->attempts,
			'max_attempts'        => $record->max_attempts,
			'available_at'        => $record->available_at->format( DATE_ATOM ),
			'cancel_requested_at' => $record->cancel_requested_at?->format( DATE_ATOM ),
			'progress_current'    => $record->progress_current,
			'progress_total'      => $record->progress_total,
			'progress_message'    => $record->progress_message,
			'last_error_code'     => $record->last_error_code,
			'last_error_message'  => $record->last_error_message,
			'started_at'          => $record->started_at?->format( DATE_ATOM ),
			'completed_at'        => $record->completed_at?->format( DATE_ATOM ),
			'created_at'          => $record->created_at->format( DATE_ATOM ),
			'updated_at'          => $record->updated_at->format( DATE_ATOM ),
		);
	}

	/**
	 * Return a stable safe admin error envelope.
	 *
	 * @param string $code Stable machine-readable code.
	 * @param string $message Safe user-facing message.
	 * @return array{error:array{code:string,message:string}}
	 */
	private static function error( string $code, string $message ): array {
		return array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}
}
// phpcs:enable WordPress.NamingConventions
