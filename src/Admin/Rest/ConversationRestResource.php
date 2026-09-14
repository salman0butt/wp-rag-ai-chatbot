<?php
/**
 * Administrator conversation REST projection boundary.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

/**
 * Projects canonical conversation administration authorities into REST-safe responses.
 */
final class ConversationRestResource {
	/** Return one bounded conversation page. */
	public function list(): void {
	}

	/** Return one bounded conversation detail projection. */
	public function read(): void {
	}

	/** Delete one canonical conversation through the explicit administrator boundary. */
	public function delete(): void {
	}
}
