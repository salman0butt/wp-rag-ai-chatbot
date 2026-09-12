<?php
/**
 * Administrator knowledge job status and lifecycle resource.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobReadRepository;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobStatus;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobEnqueuer;
use WpRagAiChatbot\Jobs\Sync\DocumentIndexJobPayload;

// phpcs:disable WordPress.NamingConventions -- DTO keys and repository API follow the approved domain contract.
/**
 * Projects bounded job status and delegates lifecycle mutations to the M09 queue seams.
 */
final class KnowledgeJobRestResource {
	/**
	 * Create the resource.
	 *
	 * @param JobReadRepository $reader Read-only job inspection repository.
	 * @param JobRepository     $repository Existing M09 mutation repository.
	 * @param Clock             $clock Queue clock.
	 */
	public function __construct(
		private readonly JobReadRepository $reader,
		private readonly JobRepository $repository,
		private readonly Clock $clock
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
			$safe_payload = DocumentIndexJobPayload::from_array( $payload );
			$record       = ( new DocumentIndexJobEnqueuer( $this->repository ) )->enqueue( $safe_payload, $this->clock->now() );
		} catch ( JobQueueException ) {
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
			$payload = DocumentIndexJobPayload::from_array( $record->payload );
			$retry   = ( new DocumentIndexJobEnqueuer( $this->repository ) )->enqueue( $payload, $this->clock->now() );
		} catch ( JobQueueException ) {
			return self::error( 'invalid_transition', 'The requested job transition is not allowed.' );
		}

		return self::project( $retry );
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
