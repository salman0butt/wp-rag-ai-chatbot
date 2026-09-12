<?php
/**
 * Public chat rate-limit persistence boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

/**
 * Atomically consumes one bounded public-chat rate-limit bucket.
 */
interface PublicChatRateLimitStore {
	/**
	 * Consume one request from a bounded bucket.
	 *
	 * @param string $bucket Opaque storage key without raw client identity.
	 * @param int    $limit Maximum allowed requests in the window.
	 * @param int    $window_seconds Window length in seconds.
	 */
	public function consume( string $bucket, int $limit, int $window_seconds ): bool;
}
