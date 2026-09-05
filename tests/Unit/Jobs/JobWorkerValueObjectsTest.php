<?php
/**
 * M09 worker value-object and clock behavior tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\Clock;
use WpRagAiChatbot\Jobs\JobExecutionException;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\WorkerConfig;

/**
 * Defines bounded Task 3 worker inputs and normalized handler failures.
 */
final class JobWorkerValueObjectsTest extends TestCase {
	/**
	 * Worker defaults match the approved bounded execution design.
	 */
	public function test_worker_config_defaults_are_bounded(): void {
		$config = new WorkerConfig();

		self::assertSame( 10, $config->max_jobs );
		self::assertSame( 20, $config->start_budget_seconds );
		self::assertSame( 120, $config->lease_seconds );
	}

	/**
	 * Invalid worker bounds fail before any job claim is attempted.
	 */
	public function test_worker_config_rejects_invalid_bounds(): void {
		foreach (
			array(
				array( 0, 20, 120 ),
				array( 101, 20, 120 ),
				array( 10, 0, 120 ),
				array( 10, 301, 120 ),
				array( 10, 20, 29 ),
				array( 10, 20, 901 ),
			) as $values
		) {
			try {
				new WorkerConfig( $values[0], $values[1], $values[2] );
				self::fail( 'Invalid worker bounds must be rejected.' );
			} catch ( JobQueueException ) {
				self::addToAssertionCount( 1 );
			}
		}
	}

	/**
	 * Normalized execution failures carry only explicit safe persisted fields.
	 */
	public function test_execution_exception_exposes_normalized_failure(): void {
		$error = new JobExecutionException( 'PROVIDER_TIMEOUT', 'Provider request timed out.', true );

		self::assertSame( 'PROVIDER_TIMEOUT', $error->safe_code );
		self::assertSame( 'Provider request timed out.', $error->safe_message );
		self::assertTrue( $error->retryable );
	}

	/**
	 * The injected clock exposes deterministic UTC-compatible time values.
	 */
	public function test_clock_contract_exposes_current_time(): void {
		self::assertTrue( method_exists( Clock::class, 'now' ) );

		$now   = new DateTimeImmutable( '2026-09-05T05:00:00+00:00' );
		$clock = new class( $now ) implements Clock {
			/**
			 * Build a fixed test clock.
			 *
			 * @param DateTimeImmutable $current Fixed current time.
			 */
			public function __construct( private readonly DateTimeImmutable $current ) {
			}

			/**
			 * Return the fixed current time.
			 */
			public function now(): DateTimeImmutable {
				return $this->current;
			}
		};

		self::assertSame( $now, $clock->now() );
	}
}
