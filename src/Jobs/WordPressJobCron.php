<?php
/**
 * WordPress cron boundary for bounded job execution.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Schedules and executes the shared bounded job runner.
 */
final class WordPressJobCron {
	public const HOOK = 'wp_rag_ai_jobs_run';

	/**
	 * Create the cron boundary.
	 *
	 * @param JobRunner $runner Shared bounded job runner.
	 */
	public function __construct( private readonly JobRunner $runner ) {
	}

	/**
	 * Register the callback and schedule the stable hook only if absent.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::HOOK );
		}
	}

	/**
	 * Execute the shared worker with the default bounded configuration.
	 */
	public function run(): JobWorkerResult {
		return $this->runner->run( new WorkerConfig() );
	}

	/**
	 * Clear all scheduled instances of the stable hook.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
