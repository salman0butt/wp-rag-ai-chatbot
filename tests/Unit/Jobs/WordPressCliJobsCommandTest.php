<?php
/**
 * WordPress CLI jobs command tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobQueueException;
use WpRagAiChatbot\Jobs\JobRunner;
use WpRagAiChatbot\Jobs\JobWorkerResult;
use WpRagAiChatbot\Jobs\WorkerConfig;
use WpRagAiChatbot\Jobs\WordPressCliJobsCommand;

/**
 * Verifies CLI availability, bounds, and shared worker delegation.
 */
final class WordPressCliJobsCommandTest extends TestCase {
	/** CLI limit controls only the worker max-job bound. */
	public function test_run_delegates_bounded_limit_to_shared_worker(): void {
		$runner  = $this->runner( 4 );
		$command = new WordPressCliJobsCommand( $runner );
		$result  = $command->run( array( 'limit' => '7' ) );

		self::assertSame( 4, $result->started_jobs );
		self::assertCount( 1, $runner->configs );
		self::assertSame( 7, $runner->configs[0]->max_jobs );
		self::assertSame( 20, $runner->configs[0]->start_budget_seconds );
		self::assertSame( 120, $runner->configs[0]->lease_seconds );
	}

	/** Invalid CLI limits are rejected before worker execution. */
	public function test_run_rejects_limit_outside_one_to_one_hundred(): void {
		$runner  = $this->runner();
		$command = new WordPressCliJobsCommand( $runner );

		$this->expectException( JobQueueException::class );
		$command->run( array( 'limit' => '101' ) );
	}

	/** Non-numeric CLI limits are rejected before worker execution. */
	public function test_run_rejects_non_numeric_limit(): void {
		$runner  = $this->runner();
		$command = new WordPressCliJobsCommand( $runner );

		$this->expectException( JobQueueException::class );
		$command->run( array( 'limit' => 'many' ) );
	}

	/** Registration is a safe no-op when WP-CLI is unavailable. */
	public function test_registration_is_safe_without_wp_cli(): void {
		self::assertFalse( WordPressCliJobsCommand::register_if_available( $this->runner() ) );
	}

	/**
	 * Build a recording worker runner.
	 *
	 * @param int $started_jobs Started-job result to expose.
	 * @return JobRunner&object{configs:list<WorkerConfig>}
	 */
	private function runner( int $started_jobs = 0 ): JobRunner {
		return new class( $started_jobs ) implements JobRunner {
			/** @var list<WorkerConfig> Recorded worker configurations. */
			public array $configs = array();

			/** @param int $started_jobs Started-job result to expose. */
			public function __construct( private readonly int $started_jobs ) {
			}

			/** Execute the fake worker and record its configuration. */
			public function run( WorkerConfig $config ): JobWorkerResult {
				$this->configs[] = $config;
				return new JobWorkerResult( $this->started_jobs );
			}
		};
	}
}
