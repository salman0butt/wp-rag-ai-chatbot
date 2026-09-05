<?php
/**
 * M09 immutable job record and repository contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WpRagAiChatbot\Jobs\JobRecord;
use WpRagAiChatbot\Jobs\JobRepository;
use WpRagAiChatbot\Jobs\JobRequest;
use WpRagAiChatbot\Jobs\JobStatus;

/**
 * Verifies Task 1 exposes the immutable hydrated record and enqueue boundary.
 */
final class JobRecordRepositoryContractTest extends TestCase {
	/**
	 * A hydrated job record preserves persisted queue state immutably.
	 */
	public function test_job_record_preserves_hydrated_state(): void {
		if ( ! class_exists( JobRecord::class ) ) {
			self::fail( 'JobRecord contract is missing.' );
		}

		$available = new DateTimeImmutable( '2026-09-05 00:00:00+00:00' );
		$created   = new DateTimeImmutable( '2026-09-04 23:59:00+00:00' );
		$updated   = new DateTimeImmutable( '2026-09-05 00:00:01+00:00' );
		$record    = new JobRecord(
			1,
			'job_abc123',
			'index.document',
			JobStatus::QUEUED,
			'document:42:v7',
			array( 'document_id' => 42 ),
			0,
			3,
			$available,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			null,
			$created,
			$updated
		);

		self::assertSame( 1, $record->id );
		self::assertSame( 'job_abc123', $record->job_key );
		self::assertSame( 'index.document', $record->type );
		self::assertSame( JobStatus::QUEUED, $record->status );
		self::assertSame( array( 'document_id' => 42 ), $record->payload );
		self::assertSame( $available, $record->available_at );
		self::assertSame( $created, $record->created_at );
		self::assertSame( $updated, $record->updated_at );
	}

	/**
	 * Task 1 exposes the enqueue persistence boundary used by Task 2.
	 */
	public function test_job_repository_exposes_enqueue_boundary(): void {
		if ( ! interface_exists( JobRepository::class ) ) {
			self::fail( 'JobRepository contract is missing.' );
		}

		$method     = new ReflectionMethod( JobRepository::class, 'enqueue' );
		$parameters = $method->getParameters();

		self::assertCount( 2, $parameters );
		self::assertSame( JobRequest::class, (string) $parameters[0]->getType() );
		self::assertSame( DateTimeImmutable::class, (string) $parameters[1]->getType() );
		self::assertSame( JobRecord::class, (string) $method->getReturnType() );
	}
}
