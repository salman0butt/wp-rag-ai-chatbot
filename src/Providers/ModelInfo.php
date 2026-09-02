<?php
/**
 * Normalized provider model metadata.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Immutable model metadata supplied explicitly by an upstream provider.
 */
final readonly class ModelInfo {
	/**
	 * Create normalized model metadata.
	 *
	 * @param string               $provider_id Stable provider ID.
	 * @param string               $model_id Stable provider model ID.
	 * @param string               $display_name Human-readable model name.
	 * @param string[]             $input_modalities Explicit input modalities.
	 * @param string[]             $output_modalities Explicit output modalities.
	 * @param string[]             $capabilities Explicit provider capabilities/parameters.
	 * @param int|null             $context_window Explicit context window when known.
	 * @param array<string, mixed> $provider_metadata Safe upstream metadata.
	 * @throws InvalidArgumentException When required identifiers or context metadata are invalid.
	 */
	public function __construct(
		public string $provider_id,
		public string $model_id,
		public string $display_name,
		public array $input_modalities = array(),
		public array $output_modalities = array(),
		public array $capabilities = array(),
		public ?int $context_window = null,
		public array $provider_metadata = array()
	) {
		if ( '' === trim( $provider_id ) ) {
			throw new InvalidArgumentException( 'Provider ID must not be empty.' );
		}
		if ( '' === trim( $model_id ) ) {
			throw new InvalidArgumentException( 'Model ID must not be empty.' );
		}
		if ( null !== $context_window && $context_window < 1 ) {
			throw new InvalidArgumentException( 'Context window must be positive when provided.' );
		}
	}
}
