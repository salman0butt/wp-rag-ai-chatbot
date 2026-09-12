<?php
/**
 * Minimal WordPress REST request test double.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

/**
 * Supplies bounded JSON payloads to REST callback contract tests.
 */
final class WP_REST_Request {
	/**
	 * Create one request double.
	 *
	 * @param array<string,mixed> $payload JSON payload.
	 */
	public function __construct( private readonly array $payload ) {
	}

	/**
	 * Return request JSON parameters.
	 *
	 * @return array<string,mixed>
	 */
	public function get_json_params(): array {
		return $this->payload;
	}
}
