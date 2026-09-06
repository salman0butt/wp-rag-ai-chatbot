<?php
/**
 * Deterministic bounded conversation memory assembly.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Memory;

use WpRagAiChatbot\Conversations\ConversationMessage;

/**
 * Builds the request-local bounded memory window.
 */
final class MemoryAssembler {
	private const MAX_MESSAGES = 12;
	private const MAX_TEXT_BYTES = 24576;

	/**
	 * Create the assembler.
	 *
	 * @param ConversationHistory $history Owner-scoped history source.
	 */
	public function __construct( private readonly ConversationHistory $history ) {
	}

	/**
	 * Assemble bounded memory for one owner-scoped conversation.
	 *
	 * @param string $conversation_id Stable conversation identifier.
	 * @param string $owner_scope Trusted owner scope.
	 */
	public function assemble( string $conversation_id, string $owner_scope ): ConversationMemory {
		$messages = $this->history->recent_for_owner( $conversation_id, $owner_scope, self::MAX_MESSAGES );
		$messages = array_slice( array_values( $messages ), -self::MAX_MESSAGES );

		$selected = array();
		$bytes    = 0;

		for ( $index = count( $messages ) - 1; $index >= 0; --$index ) {
			$message       = $messages[ $index ];
			$message_bytes = strlen( $message->content );

			if ( $bytes + $message_bytes > self::MAX_TEXT_BYTES ) {
				break;
			}

			array_unshift( $selected, $message );
			$bytes += $message_bytes;
		}

		$summary         = $this->history->summary_for_owner( $conversation_id, $owner_scope );
		$summary_version = null;
		$summary_text    = null;

		if ( null !== $summary ) {
			$candidate_text = trim( $summary['text'] );
			if ( $summary['version'] > 0 && '' !== $candidate_text && $bytes + strlen( $candidate_text ) <= self::MAX_TEXT_BYTES ) {
				$summary_version = $summary['version'];
				$summary_text    = $candidate_text;
			}
		}

		return new ConversationMemory( $selected, $summary_version, $summary_text );
	}
}
