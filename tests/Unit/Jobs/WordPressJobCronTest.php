<?php
/**
 * WordPress job cron boundary tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Unit\Jobs;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use WpRagAiChatbot\Jobs\JobRunner;
use WpRagAiChatbot\Jobs\JobWorkerResult;
use WpRagAiChatbot\Jobs\WorkerConfig;
use WpRagAiChatbot\Jobs\WordPressJobCron;

/**
 * Verifies cron scheduling and delegation remain thin and bounded.
 */
final class WordPressJobCronTest extends TestCase {
	/**
	 * Start Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear Brain Monkey down after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Schedule the stable hook only when no existing event is registered.
	 */
	#[DoesNotPerformAssertions]
	public function test_register_schedules_hook_when_absent(): void {
		$runner = $this->runner();
		$cron   = new WordPressJobCron( $runner );

		Functions\expect( 'add_action' )->once()->with( WordPressJobCron::HOOK, array( $cron, 'run' ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\expect( 'wp_schedule_event' )->once()->withAnyArgs();

		$cron->register();
	}

	/**
	 * Existing schedules must not be duplicated.
	 */
	#[DoesNotPerformAssertions]
	public function test_register_does_not_duplicate_existing_schedule(): void {
		$runner = $this->runner();
		$cron   = new WordPressJobCron( $runner );

		Functions\expect( 'add_action' )->once()->with( WordPressJobCron::HOOK, array( $cron, 'run' ) );
		Functions\when( 'wp_next_scheduled' )->justReturn( 1234567890 );
		Functions\expect( 'wp_schedule_event' )->never();

		$cron->register();
	}

	/**
	 * Cron executes the shared worker with the default bounded configuration.
	 */
	public function test_run_delegates_to_shared_worker(): void {
		$runner = $this->runner( 2 );
		$cron   = new WordPressJobCron( $runner );
		$cron->run();

		self::assertCount( 1, $runner->configs );
		self::assertSame( 10, $runner->configs[0]->max_jobs );
	}

	/**
	 * Deactivation cleanup clears every event for the stable hook.
	 */
	#[DoesNotPerformAssertions]
	public function test_unschedule_clears_stable_hook(): void {
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( WordPressJobCron::HOOK );
		WordPressJobCron::unschedule();
	}

	/**
	 * Build a recording worker runner.
	 *
	 * @param int $started_jobs Started-job result to expose.
	 * @return JobRunner&object{configs:list<WorkerConfig>}
	 */
	private function runner( int $started_jobs = 0 ): JobRunner {
		return new class( $started_jobs ) implements JobRunner {
			/**
			 * Recorded worker configurations.
			 *
			 * @var list<WorkerConfig>
			 */
			public array $configs = array();

			/**
			 * Create the recording runner.
			 *
			 * @param int $started_jobs Started-job result to expose.
			 */
			public function __construct( private readonly int $started_jobs ) {
			}

			/**
			 * Execute the fake worker and record its configuration.
			 *
			 * @param WorkerConfig $config Worker configuration.
			 */
			public function run( WorkerConfig $config ): JobWorkerResult {
				$this->configs[] = $config;
				return new JobWorkerResult( $this->started_jobs );
			}
		};
	}
}
