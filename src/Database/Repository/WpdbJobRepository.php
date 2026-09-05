<?php
/**
 * WordPress database-backed durable job repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbNamedJobIdempotencyLock;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;

// phpcs:disable WordPress.NamingConventions -- Methods implement the approved M09 domain contract.
/**
 * Persists jobs using bounded scans and optimistic lease ownership predicates.
 */
final class WpdbJobRepository implements JobRepository {
	private const CANDIDATE_SCAN_LIMIT = 10;
	private const MIN_LEASE_SECONDS = 30;
	private const MAX_LEASE_SECONDS = 900;
	private const MAX_ERROR_CODE_BYTES = 100;
	private const MAX_ERROR_MESSAGE_BYTES = 500;

	/**
	 * Create the persisted queue repository.
	 *
	 * @param Connection $connection Database connection.
	 * @param TableNames $tables Site-scoped table names.
	 */
	public function __construct(
		private readonly Connection $connection,
		private readonly TableNames $tables
	) {
	}

	/**
	 * Enqueue one validated request.
	 *
	 * @param JobRequest        $request Validated queue request.
	 * @param DateTimeImmutable $now Current time.
	 */
	public function enqueue( JobRequest $request, DateTimeImmutable $now ): JobRecord {
		if ( null === $request->idempotency_key ) {
			return $this->insert_job( $request, $now );
		}

		$lock = new WpdbNamedJobIdempotencyLock(
			$this->connection,
			$request->type,
			$request->idempotency_key
		);

		if ( ! $lock->acquire() ) {
			throw new JobQueueException( 'Could not acquire the job idempotency lock.' );
		}

		try {
			$existing = $this->find_active_idempotent_job( $request->type, $request->idempotency_key );
			if ( null !== $existing ) {
				return $existing;
			}

			return $this->insert_job( $request, $now );
		} finally {
			$lock->release();
		}
	}

	/**
	 * Claim the next due or expired-leased job.
	 *
	 * @param string            $worker_token Opaque current worker token.
	 * @param DateTimeImmutable $now Current time.
	 * @param int               $lease_seconds Lease duration.
	 */
	public function claimNext( string $worker_token, DateTimeImmutable $now, int $lease_seconds ): ?JobLease {
		$this->assert_worker_token( $worker_token );
		$this->assert_lease_seconds( $lease_seconds );

		$now_sql = self::format_utc( $now );
		$due_sql = $this->connection->prepare(
			'SELECT id, available_at AS eligible_at FROM %i WHERE status IN (%s, %s) AND available_at <= %s ORDER BY available_at ASC, id ASC LIMIT %d',
			$this->tables->jobs(),
			JobStatus::QUEUED->value,
			JobStatus::RETRY_WAIT->value,
			$now_sql,
			self::CANDIDATE_SCAN_LIMIT
		);
		$expired_sql = $this->connection->prepare(
			'SELECT id, lease_expires_at AS eligible_at FROM %i WHERE status = %s AND lease_expires_at IS NOT NULL AND lease_expires_at <= %s ORDER BY lease_expires_at ASC, id ASC LIMIT %d',
			$this->tables->jobs(),
			JobStatus::RUNNING->value,
			$now_sql,
			self::CANDIDATE_SCAN_LIMIT
		);

		$candidates = array_merge(
			$this->connection->get_results( $due_sql ),
			$this->connection->get_results( $expired_sql )
		);
		usort(
			$candidates,
			static function ( array $left, array $right ): int {
				$time_order = strcmp( (string) ( $left['eligible_at'] ?? '' ), (string) ( $right['eligible_at'] ?? '' ) );
				if ( 0 !== $time_order ) {
					return $time_order;
				}
				return (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 );
			}
		);
		$candidates = array_slice( $candidates, 0, self::CANDIDATE_SCAN_LIMIT );

		$lease_expires_at = self::format_utc( $now->modify( '+' . $lease_seconds . ' seconds' ) );
		foreach ( $candidates as $candidate ) {
			$id = (int) ( $candidate['id'] ?? 0 );
			if ( $id < 1 ) {
				continue;
			}

			$claim_sql = $this->connection->prepare(
				"UPDATE %i SET status = 'running', lease_owner = %s, lease_expires_at = %s, attempts = attempts + 1, started_at = COALESCE(started_at, %s), updated_at = %s WHERE id = %d AND ((status IN ('queued', 'retry_wait') AND available_at <= %s) OR (status = 'running' AND lease_expires_at IS NOT NULL AND lease_expires_at <= %s))",
				$this->tables->jobs(),
				$worker_token,
				$lease_expires_at,
				$now_sql,
				$now_sql,
				$id,
				$now_sql,
				$now_sql
			);
			if ( 1 !== $this->connection->query( $claim_sql ) ) {
				continue;
			}

			$row = $this->find_running_owned_row( $id, $worker_token );
			if ( null === $row ) {
				throw new JobQueueException( 'Claimed job could not be reloaded with its current lease.' );
			}

			return new JobLease( $this->hydrate( $row ), $worker_token );
		}

		return null;
	}

	/**
	 * Extend a live lease for its current owner.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param DateTimeImmutable $now Current time.
	 * @param int               $lease_seconds Lease duration.
	 */
	public function heartbeat( JobLease $lease, DateTimeImmutable $now, int $lease_seconds ): JobLease {
		$this->assert_lease_seconds( $lease_seconds );
		$now_sql       = self::format_utc( $now );
		$expires_sql   = self::format_utc( $now->modify( '+' . $lease_seconds . ' seconds' ) );
		$heartbeat_sql = $this->connection->prepare(
			"UPDATE %i SET lease_expires_at = %s, updated_at = %s WHERE id = %d AND status = 'running' AND lease_owner = %s AND lease_expires_at IS NOT NULL AND lease_expires_at > %s",
			$this->tables->jobs(),
			$expires_sql,
			$now_sql,
			$lease->job->id,
			$lease->lease_owner,
			$now_sql
		);
		$this->require_single_transition( $heartbeat_sql, 'Job heartbeat lost the current lease.' );

		$row = $this->find_running_owned_row( $lease->job->id, $lease->lease_owner );
		if ( null === $row ) {
			throw new JobQueueException( 'Heartbeated job could not be reloaded with its current lease.' );
		}
		return new JobLease( $this->hydrate( $row ), $lease->lease_owner );
	}

	/**
	 * Persist monotonic progress for the current live lease.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param JobProgress       $progress Progress snapshot.
	 * @param DateTimeImmutable $now Current time.
	 */
	public function updateProgress( JobLease $lease, JobProgress $progress, DateTimeImmutable $now ): void {
		$now_sql      = self::format_utc( $now );
		$progress_sql = $this->connection->prepare(
			"UPDATE %i SET progress_current = %d, progress_total = %d, progress_message = %s, updated_at = %s WHERE id = %d AND status = 'running' AND lease_owner = %s AND lease_expires_at IS NOT NULL AND lease_expires_at > %s AND (progress_current IS NULL OR progress_current <= %d) AND (progress_total IS NULL OR progress_total = %d)",
			$this->tables->jobs(),
			$progress->current,
			$progress->total,
			$progress->message,
			$now_sql,
			$lease->job->id,
			$lease->lease_owner,
			$now_sql,
			$progress->current,
			$progress->total
		);
		$this->require_single_transition( $progress_sql, 'Job progress lost the current lease or moved backwards.' );
	}

	/**
	 * Check cooperative cancellation for the current live lease.
	 *
	 * @param JobLease $lease Current lease.
	 */
	public function cancellationRequested( JobLease $lease ): bool {
		$sql = $this->connection->prepare(
			"SELECT CASE WHEN cancel_requested_at IS NULL THEN 0 ELSE 1 END FROM %i WHERE id = %d AND status = 'running' AND lease_owner = %s LIMIT 1",
			$this->tables->jobs(),
			$lease->job->id,
			$lease->lease_owner
		);
		$result = $this->connection->get_var( $sql );
		if ( null === $result ) {
			throw new JobQueueException( 'Cancellation check lost the current lease.' );
		}
		return 1 === (int) $result;
	}

	/**
	 * Cancel queued/retry jobs directly or request cooperative running cancellation.
	 *
	 * @param string            $job_key Stable opaque job key.
	 * @param DateTimeImmutable $now Current time.
	 */
	public function requestCancellation( string $job_key, DateTimeImmutable $now ): JobRecord {
		$this->assert_job_key( $job_key );
		$current = $this->find_by_job_key( $job_key );
		if ( null === $current ) {
			throw new JobQueueException( 'Job was not found for cancellation.' );
		}

		if ( $current->status->terminal() ) {
			return $current;
		}

		$now_sql = self::format_utc( $now );
		if ( JobStatus::RUNNING === $current->status ) {
			$sql = $this->connection->prepare(
				"UPDATE %i SET cancel_requested_at = COALESCE(cancel_requested_at, %s), updated_at = %s WHERE job_key = %s AND status = 'running'",
				$this->tables->jobs(),
				$now_sql,
				$now_sql,
				$job_key
			);
		} else {
			$sql = $this->connection->prepare(
				"UPDATE %i SET status = 'cancelled', cancel_requested_at = COALESCE(cancel_requested_at, %s), lease_owner = NULL, lease_expires_at = NULL, completed_at = %s, updated_at = %s WHERE job_key = %s AND status IN ('queued', 'retry_wait')",
				$this->tables->jobs(),
				$now_sql,
				$now_sql,
				$now_sql,
				$job_key
			);
		}

		if ( 1 !== $this->connection->query( $sql ) ) {
			$latest = $this->find_by_job_key( $job_key );
			if ( null !== $latest && ( $latest->status->terminal() || null !== $latest->cancel_requested_at ) ) {
				return $latest;
			}
			throw new JobQueueException( 'Job cancellation lost a concurrent transition.' );
		}

		$updated = $this->find_by_job_key( $job_key );
		if ( null === $updated ) {
			throw new JobQueueException( 'Cancelled job could not be reloaded.' );
		}
		return $updated;
	}

	/**
	 * Complete the current non-cancelled live lease.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param DateTimeImmutable $now Current time.
	 */
	public function complete( JobLease $lease, DateTimeImmutable $now ): void {
		$now_sql = self::format_utc( $now );
		$sql     = $this->connection->prepare(
			"UPDATE %i SET status = 'succeeded', lease_owner = NULL, lease_expires_at = NULL, completed_at = %s, updated_at = %s WHERE id = %d AND status = 'running' AND lease_owner = %s AND lease_expires_at IS NOT NULL AND lease_expires_at > %s AND cancel_requested_at IS NULL",
			$this->tables->jobs(),
			$now_sql,
			$now_sql,
			$lease->job->id,
			$lease->lease_owner,
			$now_sql
		);
		$this->require_single_transition( $sql, 'Job completion lost the current lease or was cancelled.' );
	}

	/**
	 * Return a live job to retry-wait.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param string            $code Sanitized failure code.
	 * @param string            $message Sanitized failure message.
	 * @param DateTimeImmutable $available_at Earliest next claim time.
	 * @param DateTimeImmutable $now Current time.
	 */
	public function markRetry( JobLease $lease, string $code, string $message, DateTimeImmutable $available_at, DateTimeImmutable $now ): void {
		$this->assert_error_fields( $code, $message );
		$now_sql = self::format_utc( $now );
		$sql     = $this->connection->prepare(
			"UPDATE %i SET status = 'retry_wait', available_at = %s, lease_owner = NULL, lease_expires_at = NULL, last_error_code = %s, last_error_message = %s, updated_at = %s WHERE id = %d AND status = 'running' AND lease_owner = %s AND lease_expires_at IS NOT NULL AND lease_expires_at > %s AND cancel_requested_at IS NULL",
			$this->tables->jobs(),
			self::format_utc( $available_at ),
			$code,
			$message,
			$now_sql,
			$lease->job->id,
			$lease->lease_owner,
			$now_sql
		);
		$this->require_single_transition( $sql, 'Job retry transition lost the current lease or was cancelled.' );
	}

	/**
	 * Mark the current live lease terminally failed.
	 *
	 * @param JobLease          $lease Current lease.
	 * @param string            $code Sanitized failure code.
	 * @param string            $message Sanitized failure message.
	 * @param DateTimeImmutable $now Current time.
	 */
	public function markFailed( JobLease $lease, string $code, string $message, DateTimeImmutable $now ): void {
		$this->assert_error_fields( $code, $message );
		$now_sql = self::format_utc( $now );
		$sql     = $this->connection->prepare(
			"UPDATE %i SET status = 'failed', lease_owner = NULL, lease_expires_at = NULL, last_error_code = %s, last_error_message = %s, completed_at = %s, updated_at = %s WHERE id = %d AND status = 'running' AND lease_owner = %s AND lease_expires_at IS NOT NULL AND lease_expires_at > %s AND cancel_requested_at IS NULL",
			$this->tables->jobs(),
			$code,
			$message,
			$now_sql,
			$now_sql,
			$lease->job->id,
			$lease->lease_owner,
			$now_sql
		);
		$this->require_single_transition( $sql, 'Job failure transition lost the current lease or was cancelled.' );
	}

	/**
	 * Insert one new queued row and rehydrate it.
	 *
	 * @param JobRequest        $request Validated request.
	 * @param DateTimeImmutable $now Current time.
	 */
	private function insert_job( JobRequest $request, DateTimeImmutable $now ): JobRecord {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit-testable repository boundary does not require a WordPress runtime.
			$payload_json = json_encode( $request->payload, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new JobQueueException( 'Validated job payload could not be encoded.' );
		}

		$now_sql = self::format_utc( $now );
		$insert  = $this->connection->insert(
			$this->tables->jobs(),
			array(
				'job_key'         => bin2hex( random_bytes( 20 ) ),
				'type'            => $request->type,
				'status'          => JobStatus::QUEUED->value,
				'idempotency_key' => $request->idempotency_key,
				'payload_json'    => $payload_json,
				'attempts'        => 0,
				'max_attempts'    => $request->max_attempts,
				'available_at'    => $now_sql,
				'created_at'      => $now_sql,
				'updated_at'      => $now_sql,
			)
		);
		if ( 1 !== $insert ) {
			throw new JobQueueException( 'Job enqueue insert failed.' );
		}

		$row = $this->find_row_by_id( $this->connection->insert_id() );
		if ( null === $row ) {
			throw new JobQueueException( 'Enqueued job could not be reloaded.' );
		}
		return $this->hydrate( $row );
	}

	/**
	 * Find an active job with the same type and idempotency key.
	 *
	 * @param string $type Job type.
	 * @param string $idempotency_key Idempotency key.
	 */
	private function find_active_idempotent_job( string $type, string $idempotency_key ): ?JobRecord {
		$sql = $this->connection->prepare(
			"SELECT * FROM %i WHERE type = %s AND idempotency_key = %s AND status IN ('queued', 'running', 'retry_wait') ORDER BY id ASC LIMIT 1",
			$this->tables->jobs(),
			$type,
			$idempotency_key
		);
		$row = $this->connection->get_row( $sql );
		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Find one current running row by id and owner.
	 *
	 * @param int    $id Job id.
	 * @param string $worker_token Current lease token.
	 * @return array<string, mixed>|null
	 */
	private function find_running_owned_row( int $id, string $worker_token ): ?array {
		$sql = $this->connection->prepare(
			"SELECT * FROM %i WHERE id = %d AND status = 'running' AND lease_owner = %s LIMIT 1",
			$this->tables->jobs(),
			$id,
			$worker_token
		);
		return $this->connection->get_row( $sql );
	}

	/**
	 * Find one persisted row by numeric id.
	 *
	 * @param int $id Job id.
	 * @return array<string, mixed>|null
	 */
	private function find_row_by_id( int $id ): ?array {
		$sql = $this->connection->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $this->tables->jobs(), $id );
		return $this->connection->get_row( $sql );
	}

	/**
	 * Find one persisted job by stable job key.
	 *
	 * @param string $job_key Stable opaque job key.
	 */
	private function find_by_job_key( string $job_key ): ?JobRecord {
		$sql = $this->connection->prepare( 'SELECT * FROM %i WHERE job_key = %s LIMIT 1', $this->tables->jobs(), $job_key );
		$row = $this->connection->get_row( $sql );
		return null === $row ? null : $this->hydrate( $row );
	}

	/**
	 * Require a conditional transition to affect exactly one row.
	 *
	 * @param string $sql Prepared conditional update.
	 * @param string $message Safe failure message.
	 */
	private function require_single_transition( string $sql, string $message ): void {
		if ( 1 !== $this->connection->query( $sql ) ) {
			throw new JobQueueException( $message );
		}
	}

	/**
	 * Hydrate one persisted job row.
	 *
	 * @param array<string, mixed> $row Persisted row.
	 */
	private function hydrate( array $row ): JobRecord {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_decode_json_decode -- Unit-testable repository boundary does not require a WordPress runtime.
			$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true, 9, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new JobQueueException( 'Persisted job payload is invalid.' );
		}
		if ( ! is_array( $payload ) ) {
			throw new JobQueueException( 'Persisted job payload root is invalid.' );
		}

		try {
			$status = JobStatus::from( (string) ( $row['status'] ?? '' ) );
		} catch ( \ValueError ) {
			throw new JobQueueException( 'Persisted job status is invalid.' );
		}

		return new JobRecord(
			(int) ( $row['id'] ?? 0 ),
			(string) ( $row['job_key'] ?? '' ),
			(string) ( $row['type'] ?? '' ),
			$status,
			self::nullable_string( $row['idempotency_key'] ?? null ),
			$payload,
			(int) ( $row['attempts'] ?? 0 ),
			(int) ( $row['max_attempts'] ?? 0 ),
			self::parse_utc( $row['available_at'] ?? null, 'available_at' ),
			self::nullable_string( $row['lease_owner'] ?? null ),
			self::parse_nullable_utc( $row['lease_expires_at'] ?? null, 'lease_expires_at' ),
			self::parse_nullable_utc( $row['cancel_requested_at'] ?? null, 'cancel_requested_at' ),
			self::nullable_int( $row['progress_current'] ?? null ),
			self::nullable_int( $row['progress_total'] ?? null ),
			self::nullable_string( $row['progress_message'] ?? null ),
			self::nullable_string( $row['last_error_code'] ?? null ),
			self::nullable_string( $row['last_error_message'] ?? null ),
			self::parse_nullable_utc( $row['started_at'] ?? null, 'started_at' ),
			self::parse_nullable_utc( $row['completed_at'] ?? null, 'completed_at' ),
			self::parse_utc( $row['created_at'] ?? null, 'created_at' ),
			self::parse_utc( $row['updated_at'] ?? null, 'updated_at' )
		);
	}

	/**
	 * Validate an opaque worker token.
	 *
	 * @param string $worker_token Worker token.
	 */
	private function assert_worker_token( string $worker_token ): void {
		$length = strlen( $worker_token );
		if ( $length < 16 || $length > 191 ) {
			throw new JobQueueException( 'Worker lease token must contain 16 to 191 bytes.' );
		}
	}

	/**
	 * Validate lease duration bounds.
	 *
	 * @param int $lease_seconds Lease duration.
	 */
	private function assert_lease_seconds( int $lease_seconds ): void {
		if ( $lease_seconds < self::MIN_LEASE_SECONDS || $lease_seconds > self::MAX_LEASE_SECONDS ) {
			throw new JobQueueException( 'Lease duration must be between 30 and 900 seconds.' );
		}
	}

	/**
	 * Validate public job-key lookups.
	 *
	 * @param string $job_key Stable job key.
	 */
	private function assert_job_key( string $job_key ): void {
		if ( strlen( $job_key ) < 16 || strlen( $job_key ) > 191 ) {
			throw new JobQueueException( 'Job key is outside the allowed bounds.' );
		}
	}

	/**
	 * Validate bounded persisted error fields.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 */
	private function assert_error_fields( string $code, string $message ): void {
		if ( '' === $code || strlen( $code ) > self::MAX_ERROR_CODE_BYTES || strlen( $message ) > self::MAX_ERROR_MESSAGE_BYTES ) {
			throw new JobQueueException( 'Persisted job error fields are outside the allowed bounds.' );
		}
	}

	/**
	 * Convert a datetime to canonical UTC persistence format.
	 *
	 * @param DateTimeImmutable $value Datetime value.
	 */
	private static function format_utc( DateTimeImmutable $value ): string {
		return $value->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Parse a required persisted UTC datetime.
	 *
	 * @param mixed  $value Persisted value.
	 * @param string $field Field name for safe errors.
	 */
	private static function parse_utc( mixed $value, string $field ): DateTimeImmutable {
		if ( ! is_string( $value ) || '' === $value ) {
			throw new JobQueueException( 'Persisted job ' . $field . ' is invalid.' );
		}
		try {
			return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		} catch ( \Exception ) {
			throw new JobQueueException( 'Persisted job ' . $field . ' is invalid.' );
		}
	}

	/**
	 * Parse an optional persisted UTC datetime.
	 *
	 * @param mixed  $value Persisted value.
	 * @param string $field Field name for safe errors.
	 */
	private static function parse_nullable_utc( mixed $value, string $field ): ?DateTimeImmutable {
		return null === $value ? null : self::parse_utc( $value, $field );
	}

	/**
	 * Normalize an optional scalar string.
	 *
	 * @param mixed $value Persisted value.
	 */
	private static function nullable_string( mixed $value ): ?string {
		return null === $value ? null : (string) $value;
	}

	/**
	 * Normalize an optional integer.
	 *
	 * @param mixed $value Persisted value.
	 */
	private static function nullable_int( mixed $value ): ?int {
		return null === $value ? null : (int) $value;
	}
}
// phpcs:enable WordPress.NamingConventions
