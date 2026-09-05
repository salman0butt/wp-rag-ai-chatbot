<?php
/**
 * M09 worker value-object and clock contract tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\WorkerConfig;

/**
 * Defines the public Task 3 worker value boundaries before behavior is implemented.
 */
final class JobWorkerValueObjectsTest extends TestCase {
	/**
	 * Worker configuration exposes explicit bounded fields and constructor.
	 */
	public function test_worker_config_contract_is_explicit(): void {
		self::assertTrue( method_exists( WorkerConfig::class, '__construct' ) );
		self::assertTrue( property_exists( WorkerConfig::class, 'max_jobs' ) );
		self::assertTrue( property_exists( WorkerConfig::class, 'start_budget_seconds' ) );
		self::assertTrue( property_exists( WorkerConfig::class, 'lease_seconds' ) );
	}

	/**
	 * Normalized execution failures expose only explicit safe persisted fields.
	 */
	public function test_execution_exception_contract_is_explicit(): void {
		self::assertTrue( method_exists( JobExecutionException::class, 'safe_code' ) );
		self::assertTrue( method_exists( JobExecutionException::class, 'safe_message' ) );
		self::assertTrue( method_exists( JobExecutionException::class, 'retryable' ) );
	}

	/**
	 * The injected clock exposes deterministic current time.
	 */
	public function test_clock_contract_exposes_current_time(): void {
		self::assertTrue( method_exists( Clock::class, 'now' ) );
	}
}
