<?php
/**
 * Public chat WordPress request adapter.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

/**
 * Converts trusted WordPress request inputs into bounded public chat contracts.
 */
final class PublicChatWordPressRequestAdapter {
	/**
	 * Parse one public request payload through the closed request contract.
	 *
	 * @param array<string, mixed> $payload Public request payload.
	 */
	public static function request( array $payload ): PublicChatRequest {
		return PublicChatRequest::from_array( $payload );
	}

	/**
	 * Derive one opaque client scope from trusted server metadata only.
	 *
	 * @param array<string, mixed> $server Trusted server variables.
	 * @param string               $secret Server-owned scope secret.
	 */
	public static function client_scope( array $server, string $secret ): string {
		$remote_addr = $server['REMOTE_ADDR'] ?? null;

		return PublicChatClientScope::derive(
			is_string( $remote_addr ) ? $remote_addr : null,
			$secret
		);
	}
}
