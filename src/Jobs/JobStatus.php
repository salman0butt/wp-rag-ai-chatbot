<?php
/**
 * Persisted job status values.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Jobs;

/**
 * Stable persisted queue states.
 */
enum JobStatus: string {
	case QUEUED     = 'queued';
	case RUNNING    = 'running';
	case RETRY_WAIT = 'retry_wait';
	case SUCCEEDED  = 'succeeded';
	case FAILED     = 'failed';
	case CANCELLED  = 'cancelled';

	/**
	 * Whether this state is immutable and terminal.
	 */
	public function terminal(): bool {
		return in_array( $this, array( self::SUCCEEDED, self::FAILED, self::CANCELLED ), true );
	}
}
