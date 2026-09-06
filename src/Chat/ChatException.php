<?php
/**
 * Stable chat orchestration exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use InvalidArgumentException;
use RuntimeException;

/**
 * Exposes only application-owned failure categories and sanitized messages.
 */
final class ChatException extends RuntimeException {
	/**
	 * Create one sanitized chat failure.
	 *
	 * @param ChatFailureReason $reason Stable application-owned failure reason.
	 * @param string            $safe_message Sanitized diagnostic safe for transport mapping.
	 * @throws InvalidArgumentException When the safe message is blank.
	 */
	public function __construct( public readonly ChatFailureReason $reason, string $safe_message ) {
		if ( '' === trim( $safe_message ) ) {
			throw new InvalidArgumentException( 'Chat exception safe message must not be blank.' );
		}

		parent::__construct( $safe_message );
	}
}
