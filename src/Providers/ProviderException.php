<?php
/**
 * Normalized provider exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use RuntimeException;

/**
 * Provider failure with stable, already-sanitized metadata.
 */
final class ProviderException extends RuntimeException {
	/**
	 * Create a normalized provider exception.
	 *
	 * @param ProviderErrorCode $error_code Normalized failure category.
	 * @param string            $provider_id Stable provider ID.
	 * @param string            $message Already-sanitized message.
	 * @param string|null       $request_id Safe request identifier when available.
	 */
	public function __construct(
		public readonly ProviderErrorCode $error_code,
		public readonly string $provider_id,
		string $message,
		public readonly ?string $request_id = null
	) {
		parent::__construct( $message );
	}
}
