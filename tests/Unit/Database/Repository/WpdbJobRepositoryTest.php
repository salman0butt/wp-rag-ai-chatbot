<?php
/**
 * M09 persisted job repository tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Database\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Database\Connection;
use WpRagAiChatbot\Database\Repository\WpdbJobRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;

/**
 * Defines atomic queue persistence and current-lease transition behavior.
 */
final class WpdbJobRepositoryTest extends TestCase {
	/**
	 * Non-idempotent enqueue inserts one opaque queued job and rehydrates it.
	 */
	public function test_non_idempotent_enqueue_inserts_one_queued_job(): void {
		self::assertTrue( class_exists( WpdbJobRepository::class ), 'Task 2 requires WpdbJobRepository.' );

		$connection      = $this->connection();
		$inserted_jobkey = null;
		$connection->expects( self::once() )->method( 'insert' )->willReturnCallback(
			static function ( string $table, array $data ) use ( &$inserted_jobkey ): int {
				self::assertSame( 'wp_rag_ai_jobs', $table );
				$inserted_jobkey = $data['job_key'] ?? null;
				self::assertIsString( $inserted_jobkey );
				self::assertGreaterThanOrEqual( 32, strlen( $inserted_jobkey ) );
				self::assertSame( 'queued', $data['status'] ?? null );
				self::assertSame( 0, $data['attempts'] ?? null );
				return 1;
			}
		);
		$connection->method( 'insert_id' )->willReturn( 7 );
		$connection->method( 'prepare' )->willReturn( 'job-row' );
		$connection->method( 'get_row' )->willReturnCallback(
			static function () use ( &$inserted_jobkey ): array {
				return self::row( 7, (string) $inserted_jobkey, JobStatus::QUEUED );
			}
		);

		$record = $this->repository( $connection )->enqueue(
			new JobRequest( 'index.document', array( 'document_id' => 42 ) ),
			self::utc( '2026-09-05 01:00:00' )
		);

		self::assertSame( 7, $record->id );
		self::assertSame( JobStatus::QUEUED, $record->status );
		self::assertSame( $inserted_jobkey, $record->job_key );
	}

	/**
	 * Idempotent enqueue returns the active matching job and never inserts a duplicate.
	 */
	public function test_idempotent_enqueue_returns_existing_active_job(): void {
		self::assertTrue( class_exists( WpdbJobRepository::class ), 'Task 2 requires WpdbJobRepository.' );

		$connection = $this->connection();
		$connection->expects( self::exactly( 3 ) )->method( 'prepare' )->willReturnOnConsecutiveCalls( 'lock', 'active', 'release' );
		$connection->expects( self::exactly( 2 ) )->method( 'get_var' )->willReturn( 1 );
		$connection->expects( self::once() )->method( 'get_row' )->with( 'active' )->willReturn(
			self::row( 4, 'existing-job-key', JobStatus::RUNNING, 'document:42', 'lease-owner-0123456789012345' )
		);
		$connection->expects( self::never() )->method( 'insert' );

		$record = $this->repository( $connection )->enqueue(
			new JobRequest( 'index.document', array( 'document_id' => 42 ), 'document:42' ),
			self::utc( '2026-09-05 01:00:00' )
		);

		self::assertSame( 'existing-job-key', $record->job_key );
		self::assertSame( JobStatus::RUNNING, $record->status );
	}

	/**
	 * The short idempotency lock is released even when insertion fails.
	 */
	public function test_idempotent_enqueue_releases_lock_when_insert_fails(): void {
		self::assertTrue( class_exists( WpdbJobRepository::class ), 'Task 2 requires WpdbJobRepository.' );

		$connection = $this->connection();
		$connection->expects( self::exactly( 3 ) )->method( 'prepare' )->willReturnOnConsecutiveCalls( 'lock', 'active', 'release' );
		$connection->expects( self::exactly( 2 ) )->method( 'get_var' )->willReturn( 1 );
		$connection->expects( self::once() )->method( 'get_row' )->with( 'active' )->willReturn( null );
		$connection->expects( self::once() )->method( 'insert' )->willReturn( false );

		$this->expectException( JobQueueException::class );
		$this->repository( $connection )->enqueue(
			new JobRequest( 'index.document', array( 'document_id' => 42 ), 'document:42' ),
			self::utc( '2026-09-05 01:00:00' )
		);
	}

	/**
	 * Claim scans are bounded and one successful conditional update returns the exact lease.
	 */
	public function test_claim_next_returns_current_lease_after_one_winner_update(): void {
		self::assertTrue( class_exists( WpdbJobRepository::class ), 'Task 2 requires WpdbJobRepository.' );

		$connection = $this->connection();
		$connection->expects( self::exactly( 4 ) )->method( 'prepare' )->willReturnOnConsecutiveCalls( 'due', 'expired', 'claim', 'claimed' );
		$connection->expects( self::exactly( 2 ) )->method( 'get_results' )->willReturnOnConsecutiveCalls(
			array(
				array(
					'id'          => 9,
					'eligible_at' => '2026-09-05 00:59:00',
				),
			),
			array()
		);
		$connection->expects( self::once() )->method( 'query' )->with( 'claim' )->willReturn( 1 );
		$connection->expects( self::once() )->method( 'get_row' )->with( 'claimed' )->willReturn(
			self::row( 9, 'queued-job-key', JobStatus::RUNNING, null, 'worker-token-01234567890123456789', 1 )
		);

		$lease = $this->repository( $connection )->claimNext(
			'worker-token-01234567890123456789',
			self::utc( '2026-09-05 01:00:00' ),
			120
		);

		self::assertInstanceOf( JobLease::class, $lease );
		self::assertSame( 9, $lease->job->id );
		self::assertSame( 'worker-token-01234567890123456789', $lease->lease_owner );
	}

	/**
	 * Stale owners fail closed when a lease-token conditional completion changes zero rows.
	 */
	public function test_stale_owner_cannot_complete_reclaimed_job(): void {
		self::assertTrue( class_exists( WpdbJobRepository::class ), 'Task 2 requires WpdbJobRepository.' );

		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertStringContainsString( 'lease_owner = %s', $query );
				self::assertStringContainsString( "status = 'running'", $query );
				self::assertContains( 'old-worker-token-012345678901234', $args );
				return 'complete';
			}
		);
		$connection->expects( self::once() )->method( 'query' )->with( 'complete' )->willReturn( 0 );

		$lease = new JobLease(
			$this->record( self::row( 11, 'reclaimed-job', JobStatus::RUNNING, null, 'old-worker-token-012345678901234', 1 ) ),
			'old-worker-token-012345678901234'
		);

		$this->expectException( JobQueueException::class );
		$this->repository( $connection )->complete( $lease, self::utc( '2026-09-05 01:02:00' ) );
	}

	/**
	 * Progress writes include current-lease ownership and a monotonic-current predicate.
	 */
	public function test_progress_update_fails_closed_when_monotonic_lease_predicate_loses(): void {
		self::assertTrue( class_exists( WpdbJobRepository::class ), 'Task 2 requires WpdbJobRepository.' );

		$connection = $this->connection();
		$connection->expects( self::once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $query, mixed ...$args ): string {
				self::assertStringContainsString( 'lease_owner = %s', $query );
				self::assertStringContainsString( 'progress_current IS NULL OR progress_current <= %d', $query );
				self::assertContains( 5, $args );
				return 'progress';
			}
		);
		$connection->expects( self::once() )->method( 'query' )->with( 'progress' )->willReturn( 0 );

		$lease = new JobLease(
			$this->record( self::row( 12, 'progress-job', JobStatus::RUNNING, null, 'worker-token-01234567890123456789', 1 ) ),
			'worker-token-01234567890123456789'
		);

		$this->expectException( JobQueueException::class );
		$this->repository( $connection )->updateProgress(
			$lease,
			new JobProgress( 5, 10, 'halfway' ),
			self::utc( '2026-09-05 01:01:00' )
		);
	}

	/**
	 * Create a strict connection mock with stable site identity.
	 */
	private function connection(): Connection&MockObject {
		$connection = $this->createMock( Connection::class );
		$connection->method( 'database_name' )->willReturn( 'wordpress_db' );
		$connection->method( 'prefix' )->willReturn( 'wp_' );
		return $connection;
	}

	/**
	 * Create the repository under test.
	 *
	 * @param Connection $connection Database connection fixture.
	 */
	private function repository( Connection $connection ): WpdbJobRepository {
		return new WpdbJobRepository( $connection, new TableNames( 'wp_' ) );
	}

	/**
	 * Hydrate a test row through the repository's public behavior shape.
	 *
	 * @param array<string, mixed> $row Persisted job row.
	 */
	private function record( array $row ): JobRecord {
		return new JobRecord(
			(int) $row['id'],
			(string) $row['job_key'],
			(string) $row['type'],
			JobStatus::from( (string) $row['status'] ),
			null === $row['idempotency_key'] ? null : (string) $row['idempotency_key'],
			array( 'document_id' => 42 ),
			(int) $row['attempts'],
			(int) $row['max_attempts'],
			self::utc( (string) $row['available_at'] ),
			null === $row['lease_owner'] ? null : (string) $row['lease_owner'],
			null === $row['lease_expires_at'] ? null : self::utc( (string) $row['lease_expires_at'] ),
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			self::utc( (string) $row['created_at'] ),
			self::utc( (string) $row['updated_at'] )
		);
	}

	/**
	 * Build a persisted row accepted by JobRecord hydration.
	 *
	 * @param int         $id Database identifier.
	 * @param string      $job_key Stable opaque job key.
	 * @param JobStatus   $status Persisted job status.
	 * @param string|null $idempotency_key Optional idempotency key.
	 * @param string|null $lease_owner Optional current lease owner.
	 * @param int         $attempts Persisted attempt count.
	 * @return array<string, mixed>
	 */
	private static function row(
		int $id,
		string $job_key,
		JobStatus $status,
		?string $idempotency_key = null,
		?string $lease_owner = null,
		int $attempts = 0
	): array {
		return array(
			'id'                  => $id,
			'job_key'             => $job_key,
			'type'                => 'index.document',
			'status'              => $status->value,
			'idempotency_key'     => $idempotency_key,
			'payload_json'        => '{"document_id":42}',
			'attempts'            => $attempts,
			'max_attempts'        => 3,
			'available_at'        => '2026-09-05 01:00:00',
			'lease_owner'         => $lease_owner,
			'lease_expires_at'    => null === $lease_owner ? null : '2026-09-05 01:05:00',
			'cancel_requested_at' => null,
			'progress_current'    => null,
			'progress_total'      => null,
			'progress_message'    => null,
			'last_error_code'     => null,
			'last_error_message'  => null,
			'started_at'          => null,
			'completed_at'        => null,
			'created_at'          => '2026-09-05 00:58:00',
			'updated_at'          => '2026-09-05 01:00:00',
		);
	}

	/**
	 * Build one deterministic UTC datetime.
	 *
	 * @param string $value UTC datetime literal.
	 */
	private static function utc( string $value ): DateTimeImmutable {
		return new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
	}
}
