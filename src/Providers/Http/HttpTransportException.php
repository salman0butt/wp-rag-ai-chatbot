<?php
/**
 * Provider HTTP transport exception.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers\Http;

use RuntimeException;
use WpRagAiChatbot\Providers\ProviderErrorCode;

/**
 * Normalized failure from the provider HTTP transport boundary.
 */
final class HttpTransportException extends RuntimeException {
	/**
	 * Create a normalized transport exception.
	 *
	 * @param ProviderErrorCode $error_code Normalized transport failure category.
	 * @param string            $message Secret-free diagnostic message.
	 */
	public function __construct(
		public readonly ProviderErrorCode $error_code,
		string $message
	) {
		parent::__construct( $message );
	}
}
