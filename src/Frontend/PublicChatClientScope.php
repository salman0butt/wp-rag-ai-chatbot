<?php
/**
 * Server-derived public chat client scope.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;

/**
 * Derives an opaque salted identity from trusted server connection metadata.
 */
final class PublicChatClientScope {
	/**
	 * Derive one lowercase SHA-256 client scope.
	 *
	 * Invalid or unavailable remote addresses intentionally collapse into one
	 * shared anonymous scope so missing identity never disables abuse controls.
	 *
	 * @param string|null $remote_addr Server-observed REMOTE_ADDR value.
	 * @param string      $secret Server-owned secret used only for HMAC derivation.
	 * @throws InvalidArgumentException When the server-owned secret is unavailable.
	 */
	public static function derive( ?string $remote_addr, string $secret ): string {
		if ( '' === trim( $secret ) ) {
			throw new InvalidArgumentException( 'Public chat client-scope secret is unavailable.' );
		}

		$packed = null;
		if ( null !== $remote_addr && false !== filter_var( $remote_addr, FILTER_VALIDATE_IP ) ) {
			$normalized = inet_pton( $remote_addr );
			if ( false !== $normalized ) {
				$packed = $normalized;
			}
		}

		return hash_hmac( 'sha256', $packed ?? 'anonymous', $secret );
	}
}
