<?php
/**
 * Job queue domain exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

use InvalidArgumentException;
use Throwable;

/**
 * Signals invalid queue contracts and state transitions.
 */
final class JobQueueException extends InvalidArgumentException {
	/**
	 * Persisted job identity available for a compensating rollback.
	 *
	 * @var int|null
	 */
	public readonly ?int $job_id;

	/**
	 * Create one queue exception.
	 *
	 * @param string         $message Safe queue failure message.
	 * @param int|null       $job_id Inserted job identity, when known.
	 * @param Throwable|null $previous Previous persistence failure.
	 */
	public function __construct( string $message = '', ?int $job_id = null, ?Throwable $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->job_id = $job_id;
	}
}
