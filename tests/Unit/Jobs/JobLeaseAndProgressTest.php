<?php
/**
 * M09 lease, progress and repository-operation contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WpRagAiChatbot\Jobs\JobLease;
use WpRagAiChatbot\Jobs\JobProgress;
use WpRagAiChatbot\Jobs\JobRepository;

/**
 * Defines the Task 2 domain and repository-operation boundary before implementation.
 */
final class JobLeaseAndProgressTest extends TestCase {
	/**
	 * Task 2 requires an immutable lease value object.
	 */
	public function test_job_lease_contract_exists(): void {
		self::assertTrue( class_exists( JobLease::class ), 'Task 2 requires JobLease.' );
	}

	/**
	 * Task 2 requires a bounded progress value object.
	 */
	public function test_job_progress_contract_exists(): void {
		self::assertTrue( class_exists( JobProgress::class ), 'Task 2 requires JobProgress.' );
	}

	/**
	 * Running transitions must be exposed only through the repository boundary.
	 */
	public function test_repository_exposes_task_two_transition_methods(): void {
		$repository = new ReflectionClass( JobRepository::class );
		$methods    = array(
			'claimNext',
			'heartbeat',
			'updateProgress',
			'cancellationRequested',
			'requestCancellation',
			'complete',
			'markRetry',
			'markFailed',
		);

		foreach ( $methods as $method ) {
			self::assertTrue( $repository->hasMethod( $method ), 'Missing JobRepository::' . $method . '().' );
		}
	}
}
