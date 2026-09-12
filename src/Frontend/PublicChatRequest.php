<?php
/**
 * Bounded public widget chat request.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Frontend;

use InvalidArgumentException;
use WpRagAiChatbot\Bots\BotId;

/**
 * Carries only public interaction input; runtime authority remains server-side.
 */
final readonly class PublicChatRequest {
	private const MAX_QUESTION_BYTES = 16384;
	private const MAX_CONVERSATION_ID_BYTES = 255;

	/**
	 * Create one validated public request.
	 *
	 * @param string      $bot_id Canonical persisted bot identifier.
	 * @param string      $question Bounded normalized question.
	 * @param string|null $conversation_id Optional bounded opaque conversation identifier.
	 */
	private function __construct(
		public string $bot_id,
		public string $question,
		public ?string $conversation_id
	) {
	}

	/**
	 * Parse one closed public request payload.
	 *
	 * @param array<string, mixed> $input Public request payload.
	 * @throws InvalidArgumentException When the public payload is invalid or contains unknown keys.
	 */
	public static function from_array( array $input ): self {
		$allowed_keys = array( 'bot_id', 'question', 'conversation_id' );
		if ( array() !== array_diff( array_keys( $input ), $allowed_keys ) ) {
			throw new InvalidArgumentException( 'Public chat request contains unsupported fields.' );
		}

		if (
			! isset( $input['bot_id'], $input['question'] )
			|| ! is_string( $input['bot_id'] )
			|| ! is_string( $input['question'] )
		) {
			throw new InvalidArgumentException( 'Public chat request is invalid.' );
		}

		$bot_id   = trim( $input['bot_id'] );
		$question = trim( $input['question'] );
		$conversation_id = $input['conversation_id'] ?? null;

		if ( null !== $conversation_id && ! is_string( $conversation_id ) ) {
			throw new InvalidArgumentException( 'Conversation identifier is invalid.' );
		}
		$conversation_id = null === $conversation_id ? null : trim( $conversation_id );

		$canonical_bot_id = ( new BotId( $bot_id ) )->value;

		if (
			'' === $question
			|| strlen( $question ) > self::MAX_QUESTION_BYTES
			|| 1 !== preg_match( '//u', $question )
		) {
			throw new InvalidArgumentException( 'Question is invalid.' );
		}

		if (
			null !== $conversation_id
			&& (
				'' === $conversation_id
				|| strlen( $conversation_id ) > self::MAX_CONVERSATION_ID_BYTES
				|| 1 !== preg_match( '//u', $conversation_id )
			)
		) {
			throw new InvalidArgumentException( 'Conversation identifier is invalid.' );
		}

		return new self( $canonical_bot_id, $question, $conversation_id );
	}
}
