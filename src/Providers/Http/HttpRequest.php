<?php
/**
 * Provider HTTP request value object.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Http;

/**
 * Immutable provider HTTP request with explicit network policy.
 */
final class HttpRequest {
	/**
	 * Create a provider HTTP request.
	 *
	 * @param string                    $provider_id Provider identifier.
	 * @param string                    $method HTTP method.
	 * @param string                    $url Fixed provider endpoint URL.
	 * @param array<string, string>     $headers Request headers.
	 * @param array<string, mixed>|null $json_body Optional JSON body.
	 * @param int                       $timeout Total timeout in seconds.
	 * @param int                       $redirection Maximum redirects.
	 */
	public function __construct(
		public readonly string $provider_id,
		public readonly string $method,
		public readonly string $url,
		public readonly array $headers,
		public readonly ?array $json_body,
		public readonly int $timeout,
		public readonly int $redirection = 0
	) {
	}
}
