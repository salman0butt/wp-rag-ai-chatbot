<?php
/**
 * Persisted lead projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Leads;

/**
 * Immutable persisted lead returned by the write boundary.
 */
final readonly class Lead {
	/**
	 * Create a persisted lead projection.
	 *
	 * @param string      $lead_id Stable lead identifier.
	 * @param string      $conversation_id Stable conversation identifier.
	 * @param string      $bot_id Explicit bot identifier.
	 * @param string|null $name Optional visitor name.
	 * @param string|null $email Optional visitor email.
	 * @param string|null $phone Optional visitor phone.
	 * @param string|null $note Optional visitor note.
	 * @param string|null $source Optional capture source.
	 * @param string      $created_at UTC creation timestamp.
	 * @param string      $updated_at UTC update timestamp.
	 */
	public function __construct(
		public string $lead_id,
		public string $conversation_id,
		public string $bot_id,
		public ?string $name,
		public ?string $email,
		public ?string $phone,
		public ?string $note,
		public ?string $source,
		public string $created_at,
		public string $updated_at
	) {
	}
}
