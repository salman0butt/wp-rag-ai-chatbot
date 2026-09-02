<?php
/**
 * Normalized provider usage.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Immutable usage values returned explicitly by providers.
 */
final readonly class Usage {
	/**
	 * Create normalized usage.
	 *
	 * @param int|null            $input_tokens Input/prompt tokens when known.
	 * @param int|null            $output_tokens Output/completion tokens when known.
	 * @param int|null            $total_tokens Total tokens when known.
	 * @param array<string,mixed> $safe_metadata Safe provider usage metadata.
	 * @throws InvalidArgumentException When a token count is negative.
	 */
	public function __construct(
		public ?int $input_tokens = null,
		public ?int $output_tokens = null,
		public ?int $total_tokens = null,
		public array $safe_metadata = array()
	) {
		foreach ( array( $input_tokens, $output_tokens, $total_tokens ) as $tokens ) {
			if ( null !== $tokens && $tokens < 0 ) {
				throw new InvalidArgumentException( 'Token counts must not be negative.' );
			}
		}
	}
}
