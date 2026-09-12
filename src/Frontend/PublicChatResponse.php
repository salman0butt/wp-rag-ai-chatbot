<?php
/**
 * Public-safe chat response projection.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use WpRagAiChatbot\Chat\ChatResult;
use WpRagAiChatbot\Citations\Citation;

/**
 * Carries only answer, conversation, and citation data needed by the widget.
 */
final readonly class PublicChatResponse {
	/**
	 * Create one public response projection.
	 *
	 * @param string                     $answer Generated or deterministic answer.
	 * @param string|null                $conversation_id Persisted conversation identifier when available.
	 * @param array<PublicChatCitation>  $citations Public-safe citation projections.
	 */
	public function __construct(
		public string $answer,
		public ?string $conversation_id,
		public array $citations
	) {
	}

	/** Project one trusted M11 result without provider usage or internal message/lineage identifiers. */
	public static function from_chat_result( ChatResult $result ): self {
		$citations = array();
		foreach ( $result->citations as $citation ) {
			if ( $citation instanceof Citation ) {
				$citations[] = PublicChatCitation::from_citation( $citation );
			}
		}

		return new self( $result->answer, $result->conversation_id, $citations );
	}
}
