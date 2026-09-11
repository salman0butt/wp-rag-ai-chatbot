<?php
/**
 * Bounded Playground REST request input.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Admin\Rest;

/**
 * Carries only persisted Playground selectors plus the bounded question.
 */
final readonly class PlaygroundRequest {
	private const MAX_SELECTOR_BYTES = 256;
	private const MAX_QUESTION_BYTES = 16384;

	/**
	 * Create one validated Playground request.
	 *
	 * @param string $bot_id Persisted bot identifier.
	 * @param int    $source_id Persisted knowledge-source identifier.
	 * @param string $collection_id Persisted collection identifier.
	 * @param string $question Bounded normalized question.
	 */
	private function __construct(
		public string $bot_id,
		public int $source_id,
		public string $collection_id,
		public string $question
	) {
	}

	/**
	 * Parse one fail-closed request payload.
	 *
	 * @param array<string, mixed>|null $input Request payload.
	 */
	public static function from_array( ?array $input ): ?self {
		if ( null === $input ) {
			return null;
		}

		$allowed_keys = array( 'bot_id', 'source_id', 'collection_id', 'question' );
		if ( array() !== array_diff( array_keys( $input ), $allowed_keys ) ) {
			return null;
		}

		if (
			! isset( $input['bot_id'], $input['source_id'], $input['collection_id'], $input['question'] )
			|| ! is_string( $input['bot_id'] )
			|| ! is_int( $input['source_id'] )
			|| ! is_string( $input['collection_id'] )
			|| ! is_string( $input['question'] )
		) {
			return null;
		}

		$bot_id        = trim( $input['bot_id'] );
		$collection_id = trim( $input['collection_id'] );
		$question      = trim( $input['question'] );

		if (
			'' === $bot_id
			|| '' === $collection_id
			|| '' === $question
			|| $input['source_id'] < 1
			|| strlen( $bot_id ) > self::MAX_SELECTOR_BYTES
			|| strlen( $collection_id ) > self::MAX_SELECTOR_BYTES
			|| strlen( $question ) > self::MAX_QUESTION_BYTES
			|| 1 !== preg_match( '//u', $bot_id )
			|| 1 !== preg_match( '//u', $collection_id )
			|| 1 !== preg_match( '//u', $question )
		) {
			return null;
		}

		return new self( $bot_id, $input['source_id'], $collection_id, $question );
	}
}
