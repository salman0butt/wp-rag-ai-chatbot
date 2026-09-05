<?php
/**
 * M09 worker contract existence tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;

/**
 * Defines the remaining Task 3 execution contracts before implementation.
 */
final class JobWorkerContractsTest extends TestCase {
	/**
	 * Retryable/terminal handler failures use a typed execution exception.
	 */
	public function test_execution_exception_contract_exists(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Jobs\\JobExecutionException' ) );
	}

	/**
	 * Cooperative cancellation uses a dedicated handler signal.
	 */
	public function test_cancelled_exception_contract_exists(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Jobs\\JobCancelledException' ) );
	}

	/**
	 * Worker time is injected for deterministic budgets and retry scheduling.
	 */
	public function test_clock_contract_exists(): void {
		self::assertTrue( interface_exists( 'WpRagAiChatbot\\Jobs\\Clock' ) );
	}

	/**
	 * Worker bounds are represented by a validated config value object.
	 */
	public function test_worker_config_contract_exists(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Jobs\\WorkerConfig' ) );
	}

	/**
	 * Task 3 exposes the bounded worker service.
	 */
	public function test_worker_contract_exists(): void {
		self::assertTrue( class_exists( 'WpRagAiChatbot\\Jobs\\JobWorker' ) );
	}
}
