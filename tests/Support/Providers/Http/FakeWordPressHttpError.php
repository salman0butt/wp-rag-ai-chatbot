<?php
/**
 * Fake WordPress HTTP error for transport tests.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Providers\Http;

/**
 * Minimal WP_Error-like object used behind a mocked is_wp_error() boundary.
 */
final class FakeWordPressHttpError {
	/**
	 * Create a fake WordPress HTTP error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 */
	public function __construct(
		private string $code,
		private string $message
	) {
	}

	/**
	 * Return the fake error code.
	 */
	public function get_error_code(): string {
		return $this->code;
	}

	/**
	 * Return the fake error message.
	 */
	public function get_error_message(): string {
		return $this->message;
	}
}
