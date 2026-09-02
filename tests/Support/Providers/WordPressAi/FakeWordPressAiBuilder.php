<?php
/**
 * WordPress AI prompt builder test double.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Tests\Support\Providers\WordPressAi;

use Throwable;

/**
 * Deterministic fake for the documented WordPress AI prompt builder methods.
 */
final class FakeWordPressAiBuilder {
	/**
	 * Recorded public builder calls in order.
	 *
	 * @var array<int, array<int, int|string>>
	 */
	public array $calls = array();

	/**
	 * Optional failure raised by generate_text_result().
	 *
	 * @var Throwable|null
	 */
	public ?Throwable $exception = null;

	/**
	 * Create a fake builder around one deterministic result.
	 *
	 * @param object $result Result returned by generate_text_result().
	 */
	public function __construct( private object $result ) {
	}

	/**
	 * Record the documented system-instruction call.
	 *
	 * @param string $instruction System instruction.
	 */
	public function using_system_instruction( string $instruction ): self {
		$this->calls[] = array( 'system', $instruction );
		return $this;
	}

	/**
	 * Record the documented maximum-token call.
	 *
	 * @param int $tokens Maximum output tokens.
	 */
	public function using_max_tokens( int $tokens ): self {
		$this->calls[] = array( 'max_tokens', $tokens );
		return $this;
	}

	/**
	 * Record the documented model-preference call.
	 *
	 * @param string $model_id Requested model identifier.
	 */
	public function using_model_preference( string $model_id ): self {
		$this->calls[] = array( 'model', $model_id );
		return $this;
	}

	/**
	 * Return the deterministic result or raise the configured failure.
	 *
	 * @throws Throwable When the test configures a generation failure.
	 */
	public function generate_text_result(): object {
		$this->calls[] = array( 'generate' );
		if ( null !== $this->exception ) {
			throw $this->exception;
		}
		return $this->result;
	}
}
