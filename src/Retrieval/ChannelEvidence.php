<?php
/**
 * Retrieval channel evidence.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Retrieval;

use InvalidArgumentException;

/**
 * Immutable diagnostic evidence contributed by one retrieval channel.
 */
final readonly class ChannelEvidence {
	/**
	 * Create one channel contribution.
	 *
	 * @param string $channel Retrieval channel identifier.
	 * @param float  $native_score Native channel score preserved for diagnostics.
	 * @param int    $rank One-based rank within the channel.
	 * @param float  $weight Configured channel weight.
	 * @param float  $rrf_contribution Reciprocal-rank contribution.
	 * @throws InvalidArgumentException When numeric evidence is invalid.
	 */
	public function __construct(
		public string $channel,
		public float $native_score,
		public int $rank,
		public float $weight,
		public float $rrf_contribution
	) {
		if (
			'' === trim( $channel ) ||
			! is_finite( $native_score ) ||
			$rank < 1 ||
			! is_finite( $weight ) ||
			$weight <= 0.0 ||
			! is_finite( $rrf_contribution ) ||
			$rrf_contribution < 0.0
		) {
			throw new InvalidArgumentException( 'Retrieval channel evidence is invalid.' );
		}
	}
}
