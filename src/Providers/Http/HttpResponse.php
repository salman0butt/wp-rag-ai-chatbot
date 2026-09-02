<?php
/**
 * Provider HTTP response value object.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Http;

/**
 * Normalized provider HTTP response.
 */
final class HttpResponse {
	/**
	 * Create a normalized provider HTTP response.
	 *
	 * @param int                  $status HTTP status code.
	 * @param array<string, mixed> $headers Response headers.
	 * @param string               $body Raw response body.
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $headers,
		public readonly string $body
	) {
	}
}
