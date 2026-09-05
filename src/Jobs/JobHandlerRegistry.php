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
	 * @throws JobQueueException Until behavioral registration is implemented.
	 */
	public function register( JobHandler $handler ): void {
		if ( '' === $handler->type() ) {
			throw new JobQueueException( 'Job handler type is required.' );
		}
		throw new JobQueueException( 'Job handler registration is not implemented yet.' );
	}

	/**
	 * Resolve one explicitly registered stable job type.
	 *
	 * @param string $type Persisted job type.
	 * @throws JobQueueException Until behavioral resolution is implemented.
	 */
	public function for_type( string $type ): JobHandler {
		if ( '' === $type ) {
			throw new JobQueueException( 'Job handler type is required.' );
		}
		throw new JobQueueException( 'Job handler resolution is not implemented yet.' );
	}
}
