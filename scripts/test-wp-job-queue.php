<?php
/**
 * Real WordPress M09 job repository smoke assertions.
 *
 * WP-CLI eval-file evaluates this file inside generated PHP, so strict_types
 * cannot be declared here because it would no longer be the first statement.
 *
 * @package WpRagAiChatbot
 */

use WpRagAiChatbot\Database\Repository\WpdbJobCleanupStore;
use WpRagAiChatbot\Database\Repository\WpdbJobRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;
use WpRagAiChatbot\Jobs\JobCleanup;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;

global $wpdb;

$fail = static function ( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
};

$utc = static fn ( string $value ): DateTimeImmutable => new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );

$connection = new WpdbConnection( $wpdb );
$tables     = new TableNames( $wpdb->prefix );
$repository = new WpdbJobRepository( $connection, $tables );
$jobs       = $tables->jobs();

$reset_jobs = static function () use ( $wpdb, $jobs ): void {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table identifier comes from TableNames.
	$wpdb->query( "DELETE FROM `{$jobs}`" );
};

/*
 * When anomalous duplicate active rows already exist, idempotent enqueue must
 * deterministically return the newest matching row as specified by M09.
 */
$reset_jobs();
foreach (
	array(
		array( 'job_key' => 'older-active-job', 'created_at' => '2026-09-05 00:00:00' ),
		array( 'job_key' => 'newer-active-job', 'created_at' => '2026-09-05 00:01:00' ),
	) as $fixture
) {
	$inserted = $wpdb->insert(
		$jobs,
		array(
			'job_key'         => $fixture['job_key'],
			'type'            => 'index.document',
			'status'          => JobStatus::QUEUED->value,
			'idempotency_key' => 'duplicate-active',
			'payload_json'    => '{"document_id":42}',
			'attempts'        => 0,
			'max_attempts'    => 3,
			'available_at'    => '2026-09-05 00:00:00',
			'created_at'      => $fixture['created_at'],
			'updated_at'      => $fixture['created_at'],
		)
	);
	if ( 1 !== $inserted ) {
		$fail( 'Could not create duplicate-active jobs fixture.' );
	}
}

$newest = $repository->enqueue(
	new JobRequest( 'index.document', array( 'document_id' => 42 ), 'duplicate-active' ),
	$utc( '2026-09-05 00:02:00' )
);
if ( 'newer-active-job' !== $newest->job_key ) {
	$fail( 'Idempotent enqueue did not return the newest active matching job.' );
}

/*
 * Hostile literal values must round-trip through prepared enqueue lookup while
 * concurrent-style claims, recovery, progress and cancellation stay bounded.
 */
$reset_jobs();
$hostile_key     = "document:' OR 1=1 --";
$hostile_payload = array(
	'document_id' => 42,
	'literal'     => "O'Reilly <script>literal</script> \" OR 1=1 --",
);
$now             = $utc( '2026-09-05 01:00:00' );
$enqueued        = $repository->enqueue(
	new JobRequest( 'index.document', $hostile_payload, $hostile_key ),
	$now
);
$duplicate       = $repository->enqueue(
	new JobRequest( 'index.document', array( 'document_id' => 999 ), $hostile_key ),
	$now
);
if ( $enqueued->job_key !== $duplicate->job_key || $hostile_payload !== $duplicate->payload ) {
	$fail( 'Idempotent enqueue did not preserve the existing hostile-literal job payload.' );
}

$worker_a = 'worker-a-token-01234567890123456789';
$worker_b = 'worker-b-token-01234567890123456789';
$lease_a  = $repository->claimNext( $worker_a, $now, 120 );
if ( null === $lease_a || $enqueued->job_key !== $lease_a->job->job_key ) {
	$fail( 'First worker did not claim the queued job.' );
}
if ( null !== $repository->claimNext( $worker_b, $now, 120 ) ) {
	$fail( 'Second worker claimed a job whose lease was still live.' );
}

$reclaim_time = $utc( '2026-09-05 01:02:01' );
$lease_b      = $repository->claimNext( $worker_b, $reclaim_time, 120 );
if ( null === $lease_b || $enqueued->job_key !== $lease_b->job->job_key || $worker_b !== $lease_b->lease_owner ) {
	$fail( 'Expired lease was not reclaimed by the second worker.' );
}

try {
	$repository->complete( $lease_a, $utc( '2026-09-05 01:02:02' ) );
	$fail( 'Stale lease owner completed a reclaimed job.' );
} catch ( JobQueueException ) {
	// Expected stale-owner rejection.
}

$lease_b = $repository->heartbeat( $lease_b, $utc( '2026-09-05 01:02:02' ), 120 );
$repository->updateProgress(
	$lease_b,
	new JobProgress( 1, 2, 'halfway' ),
	$utc( '2026-09-05 01:02:03' )
);
$running_cancelled = $repository->requestCancellation( $enqueued->job_key, $utc( '2026-09-05 01:02:04' ) );
if ( JobStatus::RUNNING !== $running_cancelled->status || null === $running_cancelled->cancel_requested_at ) {
	$fail( 'Running cancellation did not persist a cooperative cancellation request.' );
}
if ( ! $repository->cancellationRequested( $lease_b ) ) {
	$fail( 'Current worker could not observe its cancellation request.' );
}
try {
	$repository->complete( $lease_b, $utc( '2026-09-05 01:02:05' ) );
	$fail( 'Cancelled running job was allowed to complete successfully.' );
} catch ( JobQueueException ) {
	// Expected cancellation-aware completion rejection.
}

/* Direct cancellation of queued work must be terminal and unclaimable. */
$reset_jobs();
$queued = $repository->enqueue(
	new JobRequest( 'index.document', array( 'document_id' => 7 ) ),
	$utc( '2026-09-05 02:00:00' )
);
$cancelled = $repository->requestCancellation( $queued->job_key, $utc( '2026-09-05 02:00:01' ) );
if ( JobStatus::CANCELLED !== $cancelled->status || null === $cancelled->completed_at ) {
	$fail( 'Queued cancellation did not transition directly to cancelled.' );
}
if ( null !== $repository->claimNext( $worker_a, $utc( '2026-09-05 02:00:02' ), 120 ) ) {
	$fail( 'Cancelled queued job remained claimable.' );
}

/* Retry-wait work must become claimable only when its available_at is due. */
$reset_jobs();
$retry_job = $repository->enqueue(
	new JobRequest( 'index.document', array( 'document_id' => 8 ) ),
	$utc( '2026-09-05 03:00:00' )
);
$retry_lease = $repository->claimNext( $worker_a, $utc( '2026-09-05 03:00:00' ), 120 );
if ( null === $retry_lease || $retry_job->job_key !== $retry_lease->job->job_key ) {
	$fail( 'Retry fixture job could not be claimed.' );
}
$repository->markRetry(
	$retry_lease,
	'retryable',
	'safe retry diagnostic',
	$utc( '2026-09-05 03:01:00' ),
	$utc( '2026-09-05 03:00:01' )
);
if ( null !== $repository->claimNext( $worker_b, $utc( '2026-09-05 03:00:59' ), 120 ) ) {
	$fail( 'Retry-wait job became claimable before available_at.' );
}
$due_retry = $repository->claimNext( $worker_b, $utc( '2026-09-05 03:01:00' ), 120 );
if ( null === $due_retry || $retry_job->job_key !== $due_retry->job->job_key ) {
	$fail( 'Due retry-wait job did not become claimable.' );
}

/* Terminal cleanup must remain bounded and never delete active/new history. */
$reset_jobs();
$insert_cleanup_job = static function ( string $job_key, JobStatus $status, ?string $completed_at ) use ( $wpdb, $jobs, $fail ): void {
	$inserted = $wpdb->insert(
		$jobs,
		array(
			'job_key'      => $job_key,
			'type'         => 'index.document',
			'status'       => $status->value,
			'payload_json' => '{}',
			'attempts'     => 1,
			'max_attempts' => 3,
			'available_at' => '2026-09-05 03:00:00',
			'created_at'   => '2026-09-05 03:00:00',
			'updated_at'   => '2026-09-05 03:00:00',
			'completed_at' => $completed_at,
		)
	);
	if ( 1 !== $inserted ) {
		$fail( 'Could not create terminal cleanup fixture.' );
	}
};

$insert_cleanup_job( 'cleanup-old-succeeded', JobStatus::SUCCEEDED, '2026-09-05 03:00:00' );
$insert_cleanup_job( 'cleanup-old-failed', JobStatus::FAILED, '2026-09-05 03:01:00' );
$insert_cleanup_job( 'cleanup-new-cancelled', JobStatus::CANCELLED, '2026-09-05 04:30:00' );
$insert_cleanup_job( 'cleanup-active-running', JobStatus::RUNNING, '2026-09-05 03:00:00' );

$cleanup       = new JobCleanup( new WpdbJobCleanupStore( $connection, $tables ) );
$cleanup_time  = $utc( '2026-09-05 04:00:00' );
$first_deleted = $cleanup->prune( $cleanup_time, 1 );
if ( 1 !== $first_deleted ) {
	$fail( 'Bounded terminal cleanup did not delete exactly one row.' );
}

$job_exists = static function ( string $job_key ) use ( $wpdb, $jobs ): bool {
	$count = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE job_key = %s',
			$jobs,
			$job_key
		)
	);
	return 1 === (int) $count;
};

if ( $job_exists( 'cleanup-old-succeeded' ) ) {
	$fail( 'Cleanup did not delete the oldest eligible terminal job first.' );
}
if ( ! $job_exists( 'cleanup-old-failed' ) ) {
	$fail( 'Cleanup exceeded the requested one-row limit.' );
}

$second_deleted = $cleanup->prune( $cleanup_time );
if ( 1 !== $second_deleted ) {
	$fail( 'Default cleanup pass did not delete the remaining eligible terminal row.' );
}
if ( ! $job_exists( 'cleanup-new-cancelled' ) ) {
	$fail( 'Cleanup deleted terminal history newer than the cutoff.' );
}
if ( ! $job_exists( 'cleanup-active-running' ) ) {
	$fail( 'Cleanup deleted a non-terminal job.' );
}

$reset_jobs();