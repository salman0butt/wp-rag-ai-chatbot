<?php
/**
 * Normalized embedding usage.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Distinguishes unknown provider usage from a known zero value.
 */
final class EmbeddingUsage {
	/**
	 * Create normalized embedding usage.
	 *
	 * @param bool     $known Whether usage was reported.
	 * @param int|null $input_tokens Input token count when known.
	 * @throws InvalidArgumentException When usage values are inconsistent.
	 */
	private function __construct(
		public readonly bool $known,
		public readonly ?int $input_tokens
	) {
		if ( $known && ( null === $input_tokens || $input_tokens < 0 ) ) {
			throw new InvalidArgumentException( 'Known embedding usage requires non-negative input tokens.' );
		}
		if ( ! $known && null !== $input_tokens ) {
			throw new InvalidArgumentException( 'Unknown embedding usage cannot contain input tokens.' );
		}
	}

	/**
	 * Create unknown usage.
	 */
	public static function unknown(): self {
		return new self( false, null );
	}

	/**
	 * Create known input-token usage.
	 *
	 * @param int $tokens Input token count.
	 */
	public static function input_tokens( int $tokens ): self {
		return new self( true, $tokens );
	}
}
