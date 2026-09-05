<?php
/**
 * WordPress composition root for M09 job execution entrypoints.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use WpRagAiChatbot\Database\Repository\WpdbJobRepository;
use WpRagAiChatbot\Database\TableNames;
use WpRagAiChatbot\Database\WpdbConnection;

/**
 * Composes one shared worker for cron and WP-CLI entrypoints.
 */
final class JobWorkerBootstrap {
	/**
	 * Register WordPress execution entrypoints.
	 */
	public static function register(): void {
		global $wpdb;

		$connection = new WpdbConnection( $wpdb );
		$tables     = new TableNames( $connection->prefix() );
		$repository = new WpdbJobRepository( $connection, $tables );
		$worker     = new JobWorker( $repository, new JobHandlerRegistry(), new SystemUtcClock() );

		$cron = new WordPressJobCron( $worker );
		$cron->register();
		WordPressCliJobsCommand::register_if_available( $worker );
	}
}
