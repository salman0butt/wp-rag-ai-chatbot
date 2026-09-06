<?php
/**
 * M09 typed handler for queued document-index synchronization.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs\Sync;

use WpRagAiChatbot\Database\DatabaseException;
use WpRagAiChatbot\Jobs\JobCancelledException;
use WpRagAiChatbot\Jobs\JobExecutionContext;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\JobHandler;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Providers\ProviderErrorCode;
use WpRagAiChatbot\Providers\ProviderException;
use WpRagAiChatbot\VectorStore\VectorStoreErrorCode;
use WpRagAiChatbot\VectorStore\VectorStoreException;

/**
 * Reconstructs current indexing state server-side and delegates accepted M07/M08 work.
 */
final class DocumentIndexJobHandler implements JobHandler {
	/**
	 * Create one document-index handler.
	 *
	 * @param DocumentIndexDependencies $dependencies Server-side planning/execution boundary.
	 */
	public function __construct( private readonly DocumentIndexDependencies $dependencies ) {
	}

	/**
	 * Return the stable persisted job type.
	 */
	public function type(): string {
		return DocumentIndexJobEnqueuer::TYPE;
	}

	/**
	 * Execute one claimed document-index synchronization job.
	 *
	 * @param JobRecord           $job Current persisted running job.
	 * @param JobExecutionContext $context Current lease execution context.
	 * @throws JobCancelledException When cooperative cancellation is requested.
	 * @throws JobExecutionException When a normalized dependency failure must be persisted safely.
	 */
	public function handle( JobRecord $job, JobExecutionContext $context ): void {
		$payload = DocumentIndexJobPayload::from_array( $job->payload );

		try {
			$this->throw_if_cancelled( $context );
			$context->update_progress( new JobProgress( 0, 2, 'Planning index changes' ) );
			$plan = $this->dependencies->plan( $payload );

			$context->heartbeat();
			$this->throw_if_cancelled( $context );
			$context->update_progress( new JobProgress( 1, 2, 'Applying index changes' ) );
			$this->dependencies->execute( $payload, $plan );
			$context->update_progress( new JobProgress( 2, 2, 'Index synchronization complete' ) );
		} catch ( ProviderException $error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal queue exception contains only constant sanitized text plus a fixed enum-derived code.
			throw $this->provider_failure( $error );
		} catch ( VectorStoreException $error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal queue exception contains only constant sanitized text plus a fixed enum-derived code.
			throw $this->vector_failure( $error );
		} catch ( DatabaseException ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal queue exception contains only constant sanitized text.
			throw $this->projection_failure();
		}
	}

	/**
	 * Translate one normalized provider failure into a safe queue failure.
	 *
	 * @param ProviderException $error Normalized provider failure.
	 */
	private function provider_failure( ProviderException $error ): JobExecutionException {
		$retryable = in_array(
			$error->error_code,
			array(
				ProviderErrorCode::RATE_LIMIT,
				ProviderErrorCode::TIMEOUT,
				ProviderErrorCode::TRANSPORT,
				ProviderErrorCode::UPSTREAM_SERVER,
			),
			true
		);

		return new JobExecutionException(
			'index_provider_' . $error->error_code->value,
			$retryable
				? 'Document indexing provider is temporarily unavailable.'
				: 'Document indexing provider configuration is invalid.',
			$retryable
		);
	}

	/**
	 * Translate one normalized vector-store failure into a safe queue failure.
	 *
	 * @param VectorStoreException $error Normalized vector-store failure.
	 */
	private function vector_failure( VectorStoreException $error ): JobExecutionException {
		$retryable = in_array(
			$error->error_code,
			array(
				VectorStoreErrorCode::UNAVAILABLE,
				VectorStoreErrorCode::OPERATION_FAILED,
			),
			true
		);

		return new JobExecutionException(
			'index_vector_' . $error->error_code->value,
			$retryable
				? 'Document indexing vector store is temporarily unavailable.'
				: 'Document indexing vector configuration is invalid.',
			$retryable
		);
	}

	/**
	 * Translate local search-projection persistence failures into a safe retryable queue failure.
	 */
	private function projection_failure(): JobExecutionException {
		return new JobExecutionException(
			'index_projection_unavailable',
			'Document search projection is temporarily unavailable.',
			true
		);
	}

	/**
	 * Abort cooperatively when cancellation is requested for the current lease.
	 *
	 * @param JobExecutionContext $context Current lease execution context.
	 * @throws JobCancelledException When cancellation is requested.
	 */
	private function throw_if_cancelled( JobExecutionContext $context ): void {
		if ( $context->cancellation_requested() ) {
			throw new JobCancelledException( 'Document index synchronization was cancelled.' );
		}
	}
}
