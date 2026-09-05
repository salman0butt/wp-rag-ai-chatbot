<?php
/**
 * M09 worker value-object and clock behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\WorkerConfig;

/**
 * Defines the public Task 3 worker value boundaries and behavior.
 */
final class JobWorkerValueObjectsTest extends TestCase {
	/**
	 * Worker configuration defaults to the approved bounded invocation values.
	 */
	public function test_worker_config_defaults_are_bounded(): void {
		$config = new WorkerConfig();

		self::assertSame( 10, $config->max_jobs );
		self::assertSame( 20, $config->start_budget_seconds );
		self::assertSame( 120, $config->lease_seconds );
	}

	/**
	 * Worker configuration accepts explicit values inside approved bounds.
	 */
	public function test_worker_config_accepts_explicit_bounds(): void {
		$config = new WorkerConfig( 25, 30, 300 );

		self::assertSame( 25, $config->max_jobs );
		self::assertSame( 30, $config->start_budget_seconds );
		self::assertSame( 300, $config->lease_seconds );
	}

	/**
	 * Invalid worker configuration is rejected before queue work starts.
	 */
	public function test_worker_config_rejects_invalid_bounds(): void {
		$this->expectException( JobQueueException::class );
		new WorkerConfig( 0, 20, 120 );
	}

	/**
	 * Normalized execution failures expose only explicit safe persisted fields.
	 */
	public function test_execution_exception_exposes_safe_failure_fields(): void {
		$error = new JobExecutionException( 'provider_timeout', 'Temporary provider failure.', true );

		self::assertSame( 'provider_timeout', $error->safe_code() );
		self::assertSame( 'Temporary provider failure.', $error->safe_message() );
		self::assertTrue( $error->retryable() );
	}

	/**
	 * Unsafe persisted failure fields are rejected at construction time.
	 */
	public function test_execution_exception_rejects_invalid_safe_code(): void {
		$this->expectException( JobQueueException::class );
		new JobExecutionException( 'Provider Timeout!', 'Temporary provider failure.', true );
	}

	/**
	 * The injected clock exposes deterministic current time.
	 */
	public function test_clock_contract_exposes_current_time(): void {
		self::assertTrue( method_exists( Clock::class, 'now' ) );
	}
}
