<?php
/**
 * Normalized provider health.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

/**
 * Safe provider health/configuration snapshot.
 */
final readonly class ProviderHealth {
	/**
	 * Create a provider health snapshot.
	 *
	 * @param string               $provider_id Stable provider ID.
	 * @param ProviderHealthStatus $status Normalized health status.
	 * @param string|null          $message Safe diagnostic message.
	 * @param string|null          $request_id Safe request identifier when available.
	 */
	public function __construct(
		public string $provider_id,
		public ProviderHealthStatus $status,
		public ?string $message = null,
		public ?string $request_id = null
	) {}
}
