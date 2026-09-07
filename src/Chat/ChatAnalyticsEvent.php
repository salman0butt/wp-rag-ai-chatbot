<?php
/**
 * Sanitized chat analytics event.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Chat;

use InvalidArgumentException;

/**
 * Text-free normalized analytics emitted by the M11 chat application boundary.
 */
final readonly class ChatAnalyticsEvent {
	/**
	 * Create one safe analytics event.
	 *
	 * @param bool        $no_answer Whether the application returned controlled no-answer.
	 * @param string      $model_id Normalized requested model identifier.
	 * @param string|null $provider_id Normalized provider identifier when generation ran.
	 * @param int         $retrieval_candidate_count Final selected retrieval candidate count.
	 * @param int         $citation_count Validated citation count.
	 * @param int|null    $input_tokens Normalized provider input tokens when known.
	 * @param int|null    $output_tokens Normalized provider output tokens when known.
	 * @param int|null    $total_tokens Normalized provider total tokens when known.
	 * @throws InvalidArgumentException When a count is negative or an identifier is empty.
	 */
	public function __construct(
		public bool $no_answer,
		public string $model_id,
		public ?string $provider_id,
		public int $retrieval_candidate_count,
		public int $citation_count,
		public ?int $input_tokens = null,
		public ?int $output_tokens = null,
		public ?int $total_tokens = null
	) {
		if ( '' === trim( $model_id ) ) {
			throw new InvalidArgumentException( 'Model ID must not be empty.' );
		}
		if ( null !== $provider_id && '' === trim( $provider_id ) ) {
			throw new InvalidArgumentException( 'Provider ID must not be empty when present.' );
		}

		foreach (
			array(
				$retrieval_candidate_count,
				$citation_count,
				$input_tokens,
				$output_tokens,
				$total_tokens,
			) as $count
		) {
			if ( null !== $count && $count < 0 ) {
				throw new InvalidArgumentException( 'Analytics counts must not be negative.' );
			}
		}
	}
}
