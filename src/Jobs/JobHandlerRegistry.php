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
	/** @var array<string, JobHandler> */
	private array $handlers = array();

	/**
	 * Register one explicit stable job type.
	 *
	 * @param JobHandler $handler Typed handler implementation.
	 */
	public function register( JobHandler $handler ): void {
		$type = $handler->type();
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_.-]{0,99}$/', $type ) ) {
			throw new JobQueueException( 'Job handler type must use the stable lowercase queue grammar.' );
		}
		if ( isset( $this->handlers[ $type ] ) ) {
			throw new JobQueueException( 'Job handler type is already registered.' );
		}
		$this->handlers[ $type ] = $handler;
	}

	/**
	 * Resolve one explicitly registered stable job type.
	 *
	 * @param string $type Persisted job type.
	 */
	public function for_type( string $type ): JobHandler {
		if ( ! isset( $this->handlers[ $type ] ) ) {
			throw new JobQueueException( 'No handler is registered for this job type.' );
		}
		return $this->handlers[ $type ];
	}
}
