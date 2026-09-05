<?php
/**
 * WP-CLI boundary for bounded job execution.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Validates CLI limits and delegates to the shared runner.
 */
final class WordPressCliJobsCommand {
	/**
	 * Create the CLI boundary.
	 *
	 * @param JobRunner $runner Shared bounded job runner.
	 */
	public function __construct( private readonly JobRunner $runner ) {
	}

	/**
	 * Run the worker with a validated CLI limit.
	 *
	 * @param array<string,mixed> $assoc_args CLI associative arguments.
	 * @throws JobQueueException When the limit is not an integer in 1..100.
	 */
	public function run( array $assoc_args ): JobWorkerResult {
		$raw_limit = $assoc_args['limit'] ?? 10;
		if ( is_int( $raw_limit ) ) {
			$limit = $raw_limit;
		} elseif ( is_string( $raw_limit ) && 1 === preg_match( '/^[0-9]+$/', $raw_limit ) ) {
			$limit = (int) $raw_limit;
		} else {
			throw new JobQueueException( 'Jobs worker limit must be an integer between 1 and 100.' );
		}

		if ( $limit < 1 || $limit > 100 ) {
			throw new JobQueueException( 'Jobs worker limit must be an integer between 1 and 100.' );
		}

		return $this->runner->run( new WorkerConfig( $limit ) );
	}

	/**
	 * Register the command only when WP-CLI is available.
	 *
	 * @param JobRunner $runner Shared bounded job runner.
	 */
	public static function register_if_available( JobRunner $runner ): bool {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
			return false;
		}

		$command = new self( $runner );
		$callback = static function ( array $args, array $assoc_args ) use ( $command ): void {
			unset( $args );
			try {
				$result = $command->run( $assoc_args );
				call_user_func( array( 'WP_CLI', 'success' ), sprintf( 'Started %d job(s).', $result->started_jobs ) );
			} catch ( JobQueueException ) {
				call_user_func( array( 'WP_CLI', 'error' ), 'Invalid jobs worker limit.' );
			}
		};

		call_user_func( array( 'WP_CLI', 'add_command' ), 'wp-rag-ai jobs run', $callback );
		return true;
	}
}
