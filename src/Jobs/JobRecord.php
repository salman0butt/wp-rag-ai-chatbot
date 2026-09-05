<?php
/**
 * Immutable persisted job state.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use DateTimeImmutable;

/**
 * Hydrated persisted queue record shared by repository and worker boundaries.
 */
final class JobRecord {
	/**
	 * Create a hydrated persisted job record.
	 *
	 * @param int                  $id Database identity.
	 * @param string               $job_key Stable opaque application identity.
	 * @param string               $type Allowlisted handler type.
	 * @param JobStatus            $status Persisted queue status.
	 * @param string|null          $idempotency_key Optional active-job deduplication key.
	 * @param array<string, mixed> $payload Decoded bounded job payload.
	 * @param int                  $attempts Attempts already claimed.
	 * @param int                  $max_attempts Maximum allowed attempts.
	 * @param DateTimeImmutable    $available_at Earliest UTC claim time.
	 * @param string|null          $lease_owner Current opaque lease token.
	 * @param DateTimeImmutable|null $lease_expires_at Current UTC lease expiry.
	 * @param DateTimeImmutable|null $cancel_requested_at UTC cancellation request time.
	 * @param int|null             $progress_current Optional progress current value.
	 * @param int|null             $progress_total Optional progress total value.
	 * @param string|null          $progress_message Optional bounded safe progress message.
	 * @param string|null          $last_error_code Optional sanitized failure code.
	 * @param string|null          $last_error_message Optional sanitized failure message.
	 * @param DateTimeImmutable|null $started_at First UTC start time.
	 * @param DateTimeImmutable|null $completed_at Terminal UTC completion time.
	 * @param DateTimeImmutable    $created_at UTC creation time.
	 * @param DateTimeImmutable    $updated_at UTC last update time.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $job_key,
		public readonly string $type,
		public readonly JobStatus $status,
		public readonly ?string $idempotency_key,
		public readonly array $payload,
		public readonly int $attempts,
		public readonly int $max_attempts,
		public readonly DateTimeImmutable $available_at,
		public readonly ?string $lease_owner,
		public readonly ?DateTimeImmutable $lease_expires_at,
		public readonly ?DateTimeImmutable $cancel_requested_at,
		public readonly ?int $progress_current,
		public readonly ?int $progress_total,
		public readonly ?string $progress_message,
		public readonly ?string $last_error_code,
		public readonly ?string $last_error_message,
		public readonly ?DateTimeImmutable $started_at,
		public readonly ?DateTimeImmutable $completed_at,
		public readonly DateTimeImmutable $created_at,
		public readonly DateTimeImmutable $updated_at
	) {
	}
}
