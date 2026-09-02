<?php
/**
 * Normalized generation request.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Immutable provider-neutral text generation request.
 */
final readonly class GenerationRequest {
	/**
	 * Normalized model identifier.
	 *
	 * @var string
	 */
	public string $model_id;

	/**
	 * Original user input.
	 *
	 * @var string
	 */
	public string $input;

	/**
	 * Optional instruction text.
	 *
	 * @var string|null
	 */
	public ?string $instructions;

	/**
	 * Optional maximum output token count.
	 *
	 * @var int|null
	 */
	public ?int $max_output_tokens;

	/**
	 * Create a normalized request.
	 *
	 * @param string      $model_id Model identifier.
	 * @param string      $input User input.
	 * @param string|null $instructions Optional system/developer instruction text.
	 * @param int|null    $max_output_tokens Optional maximum output token count.
	 * @throws InvalidArgumentException When request invariants are invalid.
	 */
	public function __construct(
		string $model_id,
		string $input,
		?string $instructions = null,
		?int $max_output_tokens = null
	) {
		$model_id = trim( $model_id );

		if ( '' === $model_id ) {
			throw new InvalidArgumentException( 'Model ID must not be empty.' );
		}
		if ( '' === trim( $input ) ) {
			throw new InvalidArgumentException( 'Input must not be empty.' );
		}
		if ( null !== $max_output_tokens && ( $max_output_tokens < 1 || $max_output_tokens > 32768 ) ) {
			throw new InvalidArgumentException( 'Maximum output tokens must be between 1 and 32768.' );
		}

		$this->model_id          = $model_id;
		$this->input             = $input;
		$this->instructions      = $instructions;
		$this->max_output_tokens = $max_output_tokens;
	}
}
