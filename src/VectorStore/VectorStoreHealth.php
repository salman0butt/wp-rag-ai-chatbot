<?php
/**
 * Vector-store health value.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\VectorStore;

/**
 * Immutable adapter health state.
 */
final class VectorStoreHealth {
	/**
	 * Create a health result.
	 *
	 * @param bool   $healthy Whether the store is healthy.
	 * @param string $message Sanitized diagnostic message.
	 */
	private function __construct(
		public readonly bool $healthy,
		public readonly string $message
	) {
	}

	/**
	 * Return a healthy state.
	 */
	public static function healthy(): self {
		return new self( true, '' );
	}

	/**
	 * Return an unhealthy state.
	 *
	 * @param string $message Sanitized diagnostic message.
	 */
	public static function unhealthy( string $message ): self {
		return new self( false, $message );
	}
}
