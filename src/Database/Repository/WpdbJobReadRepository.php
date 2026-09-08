<?php
/**
 * WordPress database-backed read-only job inspection repository.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Database\Repository;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use ValueError;
use WpRagAiChatbot\Core\PagedResult;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobReadRepository;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobStatus;

// phpcs:disable WordPress.NamingConventions -- Repository API follows the approved domain contract.
/**
 * Provides bounded administrator reads over the existing M09 jobs table.
 */
final class WpdbJobReadRepository implements JobReadRepository {
	/**
	 * Create the persisted job reader.
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
	 * Find one persisted job by stable job key.
	 *
	 * @param string $job_key Stable opaque job key.
	 */
	public function findByKey( string $job_key ): ?JobRecord {
		if ( '' === trim( $job_key ) || strlen( $job_key ) > 191 ) {
			throw new JobQueueException( 'Job key is invalid.' );
		}

		$sql = $this->connection->prepare(
			'SELECT * FROM %i WHERE job_key = %s LIMIT 1',
			$this->tables->jobs(),
			$job_key
		);
		$row = $this->connection->get_row( $sql );

		return null === $row ? null : self::hydrate( $row );
	}

	/**
	 * Return one bounded newest-first page of persisted jobs.
	 *
	 * @param int $page One-based page.
	 * @param int $perPage Requested page size.
	 */
	public function paginate( int $page, int $perPage ): PagedResult {
		if ( $page < 1 || $perPage < 1 || $perPage > 100 ) {
			throw new JobQueueException( 'Job page bounds are invalid.' );
		}

		$count_sql = $this->connection->prepare(
			'SELECT COUNT(*) FROM %i',
			$this->tables->jobs()
		);
		$page_sql = $this->connection->prepare(
			'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d',
			$this->tables->jobs(),
			$perPage,
			( $page - 1 ) * $perPage
		);
		$rows = $this->connection->get_results( $page_sql );

		return new PagedResult(
			array_map( self::hydrate( ... ), $rows ),
			(int) $this->connection->get_var( $count_sql ),
			$page,
			$perPage
		);
	}

	/**
	 * Hydrate one existing M09 job row.
	 *
	 * @param array<string,mixed> $row Persisted row.
	 */
	private static function hydrate( array $row ): JobRecord {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_decode_json_decode -- Persistence layer is unit-testable without WordPress runtime.
			$payload = json_decode( (string) ( $row['payload_json'] ?? '' ), true, 9, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new JobQueueException( 'Persisted job payload is invalid.' );
		}
		if ( ! is_array( $payload ) ) {
			throw new JobQueueException( 'Persisted job payload root is invalid.' );
		}

		try {
			$status = JobStatus::from( (string) ( $row['status'] ?? '' ) );
		} catch ( ValueError ) {
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

	/** Convert a nullable persisted scalar to a string. */
	private static function nullable_string( mixed $value ): ?string {
		return null === $value ? null : (string) $value;
	}

	/** Convert a nullable persisted scalar to an integer. */
	private static function nullable_int( mixed $value ): ?int {
		return null === $value ? null : (int) $value;
	}

	/** Parse one required persisted UTC datetime. */
	private static function parse_utc( mixed $value, string $field ): DateTimeImmutable {
		if ( ! is_string( $value ) || '' === $value ) {
			throw new JobQueueException( 'Persisted job ' . $field . ' is invalid.' );
		}

		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}

	/** Parse one nullable persisted UTC datetime. */
	private static function parse_nullable_utc( mixed $value, string $field ): ?DateTimeImmutable {
		return null === $value ? null : self::parse_utc( $value, $field );
	}
}
// phpcs:enable WordPress.NamingConventions
