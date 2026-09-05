<?php
/**
 * Deterministic retrieval confidence value.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval\Confidence;

use InvalidArgumentException;

/**
 * Immutable bounded retrieval confidence signal.
 */
final readonly class RetrievalConfidence {
	/**
	 * Create one confidence value.
	 *
	 * @param float  $score Numeric score in the inclusive [0,1] range.
	 * @param string $level Enum-like confidence level: high, medium, or low.
	 * @throws InvalidArgumentException When score or level is invalid.
	 */
	public function __construct(
		public float $score,
		public string $level
	) {
		if (
			! is_finite( $score ) ||
			$score < 0.0 ||
			$score > 1.0 ||
			! in_array( $level, array( 'high', 'medium', 'low' ), true )
		) {
			throw new InvalidArgumentException( 'Retrieval confidence is invalid.' );
		}
	}
}
