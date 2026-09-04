<?php
/**
 * Vector-store exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

use RuntimeException;

/**
 * Sanitized infrastructure-neutral operational failure.
 */
final class VectorStoreException extends RuntimeException {
	/**
	 * Create a vector-store exception.
	 *
	 * @param VectorStoreErrorCode $error_code Stable error code.
	 * @param string               $message Sanitized message.
	 */
	public function __construct(
		public readonly VectorStoreErrorCode $error_code,
		string $message
	) {
		parent::__construct( $message );
	}
}
