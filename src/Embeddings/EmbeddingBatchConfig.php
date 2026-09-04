<?php
/**
 * Embedding batch configuration.
 *
 * @package WpRagAiChatbot
 */

declare(strict_types=1);

namespace WpRagAiChatbot\Embeddings;

use InvalidArgumentException;

/**
 * Bounded application-level embedding batch size.
 */
final class EmbeddingBatchConfig {
	private const MAX_INPUTS = 10000;

	/**
	 * Maximum inputs per provider request.
	 *
	 * @var int<1, 10000>
	 */
	public readonly int $max_inputs_per_batch;

	/**
	 * Create deterministic embedding batch configuration.
	 *
	 * @param int $max_inputs_per_batch Maximum inputs per provider request.
	 * @throws InvalidArgumentException When the limit is outside the supported bound.
	 */
	public function __construct( int $max_inputs_per_batch ) {
		if ( $max_inputs_per_batch < 1 || $max_inputs_per_batch > self::MAX_INPUTS ) {
			throw new InvalidArgumentException( 'Embedding batch size must be between 1 and 10000 inputs.' );
		}

		$this->max_inputs_per_batch = $max_inputs_per_batch;
	}
}
