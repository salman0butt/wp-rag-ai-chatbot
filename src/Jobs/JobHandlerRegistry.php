<?php
/**
 * Allowlisted M09 job handler registry.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Resolves only explicitly registered handler implementations.
 */
final class JobHandlerRegistry {
	/**
	 * Register one explicit stable job type.
	 *
	 * @param JobHandler $handler Typed handler implementation.
	 */
	public function register( JobHandler $handler ): void {
		throw new JobQueueException( 'Job handler registration is not implemented yet.' );
	}

	/**
	 * Resolve one explicitly registered stable job type.
	 *
	 * @param string $type Persisted job type.
	 */
	public function for_type( string $type ): JobHandler {
		throw new JobQueueException( 'Job handler resolution is not implemented yet.' );
	}
}
