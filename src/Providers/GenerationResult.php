<?php
/**
 * Normalized generation result.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Providers;

use InvalidArgumentException;

/**
 * Immutable provider-neutral generation result.
 */
final readonly class GenerationResult {
	/**
	 * Create a normalized generation result.
	 *
	 * @param string           $provider_id Stable provider ID.
	 * @param string           $model_id Model ID actually used or requested fallback.
	 * @param string           $output_text Normalized output text.
	 * @param GenerationStatus $status Normalized completion status.
	 * @param Usage            $usage Provider usage values.
	 * @param string|null      $request_id Safe provider request identifier.
	 * @throws InvalidArgumentException When a completed result has no output.
	 */
	public function __construct(
		public string $provider_id,
		public string $model_id,
		public string $output_text,
		public GenerationStatus $status,
		public Usage $usage,
		public ?string $request_id = null
	) {
		if ( GenerationStatus::COMPLETED === $status && '' === trim( $output_text ) ) {
			throw new InvalidArgumentException( 'Completed generation results must contain output text.' );
		}
	}
}
