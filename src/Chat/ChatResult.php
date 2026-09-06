<?php
/**
 * Normalized chat result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use InvalidArgumentException;
use WpRagAiChatbot\Providers\Usage;

/**
 * Immutable provider-neutral chat result.
 */
final readonly class ChatResult {
	/**
	 * Answer text.
	 *
	 * @var string
	 */
	public string $answer;

	/**
	 * Whether the result is an application-owned deterministic no-answer.
	 *
	 * @var bool
	 */
	public bool $no_answer;

	/**
	 * Normalized provider usage when generation occurred.
	 *
	 * @var Usage|null
	 */
	public ?Usage $usage;

	/**
	 * Validated application-level citation records.
	 *
	 * @var array<int,mixed>
	 */
	public array $citations;

	/**
	 * Persisted conversation identifier when available.
	 *
	 * @var string|null
	 */
	public ?string $conversation_id;

	/**
	 * Persisted assistant-message identifier when available.
	 *
	 * @var string|null
	 */
	public ?string $message_id;

	/**
	 * Create a normalized chat result.
	 *
	 * @param string           $answer Answer text.
	 * @param bool             $no_answer Whether this is a deterministic no-answer.
	 * @param Usage|null       $usage Normalized provider usage.
	 * @param array<int,mixed> $citations Validated application-level citations.
	 * @param string|null      $conversation_id Persisted conversation identifier.
	 * @param string|null      $message_id Persisted assistant-message identifier.
	 * @throws InvalidArgumentException When result invariants are invalid.
	 */
	public function __construct(
		string $answer,
		bool $no_answer = false,
		?Usage $usage = null,
		array $citations = array(),
		?string $conversation_id = null,
		?string $message_id = null
	) {
		$answer          = trim( $answer );
		$conversation_id = null === $conversation_id ? null : trim( $conversation_id );
		$message_id      = null === $message_id ? null : trim( $message_id );

		if ( '' === $answer ) {
			throw new InvalidArgumentException( 'Answer must not be empty.' );
		}
		if ( null !== $conversation_id && '' === $conversation_id ) {
			throw new InvalidArgumentException( 'Conversation ID must not be empty when supplied.' );
		}
		if ( null !== $message_id && '' === $message_id ) {
			throw new InvalidArgumentException( 'Message ID must not be empty when supplied.' );
		}

		$this->answer          = $answer;
		$this->no_answer       = $no_answer;
		$this->usage           = $usage;
		$this->citations       = array_values( $citations );
		$this->conversation_id = $conversation_id;
		$this->message_id      = $message_id;
	}
}
