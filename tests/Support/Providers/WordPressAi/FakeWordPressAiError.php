<?php
/**
 * WordPress AI error test double.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Providers\WordPressAi;

/**
 * Minimal WP_Error-compatible fake used only through public error methods.
 */
final class FakeWordPressAiError {
	/**
	 * Create a deterministic error.
	 *
	 * @param string $message Safe/error diagnostic input.
	 * @param mixed  $data Public error data.
	 */
	public function __construct(
		private string $message,
		private mixed $data
	) {
	}

	/**
	 * Return the error message.
	 */
	public function get_error_message(): string {
		return $this->message;
	}

	/**
	 * Return public error data.
	 */
	public function get_error_data(): mixed {
		return $this->data;
	}
}
