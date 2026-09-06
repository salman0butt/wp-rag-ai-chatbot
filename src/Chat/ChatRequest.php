<?php
/**
 * Normalized chat request.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use InvalidArgumentException;
use WpRagAiChatbot\RAG\GroundingMode;

/**
 * Immutable provider-neutral chat request.
 */
final readonly class ChatRequest {
	/**
	 * Maximum accepted current-question size in UTF-8 bytes.
	 */
	private const MAX_QUESTION_BYTES = 16384;

	/**
	 * Default and maximum M11 output-token request.
	 */
	private const MAX_OUTPUT_TOKENS = 4096;

	/**
	 * Current user question.
	 *
	 * @var string
	 */
	public string $question;

	/**
	 * Provider-neutral model identifier.
	 *
	 * @var string
	 */
	public string $model_id;

	/**
	 * Grounding policy mode.
	 *
	 * @var GroundingMode
	 */
	public GroundingMode $grounding_mode;

	/**
	 * Optional existing conversation identifier.
	 *
	 * @var string|null
	 */
	public ?string $conversation_id;

	/**
	 * Maximum requested generation output tokens.
	 *
	 * @var int
	 */
	public int $max_output_tokens;

	/**
	 * Create a normalized chat request.
	 *
	 * @param string        $question Current user question.
	 * @param string        $model_id Provider-neutral model identifier.
	 * @param GroundingMode $grounding_mode Grounding policy mode.
	 * @param string|null   $conversation_id Optional existing conversation identifier.
	 * @param int           $max_output_tokens Maximum requested generation output tokens.
	 * @throws InvalidArgumentException When request invariants are invalid.
	 */
	public function __construct(
		string $question,
		string $model_id,
		GroundingMode $grounding_mode,
		?string $conversation_id = null,
		int $max_output_tokens = self::MAX_OUTPUT_TOKENS
	) {
		$question        = trim( $question );
		$model_id        = trim( $model_id );
		$conversation_id = null === $conversation_id ? null : trim( $conversation_id );

		if ( '' === $question ) {
			throw new InvalidArgumentException( 'Question must not be empty.' );
		}
		if ( strlen( $question ) > self::MAX_QUESTION_BYTES ) {
			throw new InvalidArgumentException( 'Question must not exceed 16384 bytes.' );
		}
		if ( '' === $model_id ) {
			throw new InvalidArgumentException( 'Model ID must not be empty.' );
		}
		if ( null !== $conversation_id && '' === $conversation_id ) {
			throw new InvalidArgumentException( 'Conversation ID must not be empty when supplied.' );
		}
		if ( $max_output_tokens < 1 || $max_output_tokens > self::MAX_OUTPUT_TOKENS ) {
			throw new InvalidArgumentException( 'Maximum output tokens must be between 1 and 4096.' );
		}

		$this->question          = $question;
		$this->model_id          = $model_id;
		$this->grounding_mode    = $grounding_mode;
		$this->conversation_id   = $conversation_id;
		$this->max_output_tokens = $max_output_tokens;
	}
}
